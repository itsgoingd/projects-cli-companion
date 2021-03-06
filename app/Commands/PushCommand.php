<?php namespace ProjectsCliCompanion\Commands;

use ProjectsCliCompanion\Deployment\TargetsRepository;
use ProjectsCliCompanion\Metadata\Metadata;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PushCommand extends BaseCommand
{
	protected function configure()
	{
		$this
			->setName('push')
			->setDescription('Push out changes to remote server.')
			->addArgument(
				'workTime',
				InputArgument::REQUIRED,
				'Work time (eg. 4:20).'
			)
			->addOption(
				'username',
				'u',
				InputOption::VALUE_OPTIONAL,
				'Projects username.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$svn = $this->getSvn($this->config, $input, $output);
		$git = $this->getGit();
		$metadata = Metadata::loadFromPath(getcwd());

		if ($svn->getCurrentRevision() != $svn->getLatestRevision()) {
			$output->writeln('<error>Please use `projects pull` to pull in new remote changes first.</error>');

			return;
		}

		$output->write('<info>Retrieving changes to push... </info>');

		$commitsToPush = $this->getNotPushedCommits($metadata, $git);

		$output->writeln('<info>✓ (' . count($commitsToPush) . ' commits)</info>');
		$output->writeln('');

		if ($svn->getCurrentRevision() == $metadata->get('lastPushedRemoteRevision')) {
			$this->pushAll($commitsToPush, $svn, $git, $input, $output);
		} else {
			$this->pushMerged($commitsToPush, $svn, $git, $input, $output);
		}

		$svn->up();

		$this->saveMetadata($metadata, $git, $svn);

		$output->writeln('');

		$this->deployOnPushTargets($metadata, $svn, $output);
	}

	protected function pushAll($commitsToPush, $svn, $git, $input, $output)
	{
		foreach ($commitsToPush as $i => $commit) {
			$output->write("Pushing commit {$commit['shortRevision']}... ");
			$output->write('checking out... ');

			$git->checkout([ $commit['revision'] ]);

			$output->write('committing... ');

			$message = $commit['message'];

			if ($i == count($commitsToPush) - 1) {
				$message = $input->getArgument('workTime') . ' ' . $commit['message'];
			}

			$this->addNewFilesToSvn($svn, $git);
			$this->removeDeletedFilesFromSvn();

			$svn->commit([ '.', 'message' => $message ]);

			$output->writeln('✓');
		}

		$git->checkout([ 'master' ]);
	}

	protected function pushMerged($commitsToPush, $svn, $git, $input, $output)
	{
		$message = $input->getArgument('workTime') . ' ';

		foreach ($commitsToPush as $i => $commit) {
			$message .= "{$commit['message']}\n";
		}

		$output->write("Pushing commits... committing... ");

		$this->addNewFilesToSvn($svn, $git);
		$this->removeDeletedFilesFromSvn();

		$svn->commit([ '.', 'message' => $message ]);

		$output->writeln('✓');
	}

	protected function getNotPushedCommits($metadata, $git)
	{
		$gitLog = $git->log();

		$commits = [];
		$commit = [];

		foreach ($gitLog as $line) {
			if (preg_match('/^commit (?<revision>.+)$/', $line, $matches)) {
				$commits[] = $commit;

				if ($matches['revision'] == $metadata->get('lastPushedRevision')) {
					break;
				}

				$commit = [
					'revision'      => $matches['revision'],
					'shortRevision' => $git->getShortRevision($matches['revision']),
					'message'       => ''
				];
			} elseif (strpos($line, 'Author:') !== 0 && strpos($line, 'Date:') !== 0 && trim($line) != '') {
				$commit['message'] .= trim($line) . "\n";
			}
		}

		array_shift($commits);

		$commits = array_reverse($commits);

		$commits = array_filter($commits, function($commit)
		{
			return strpos($commit['message'], '[IMPORT]') !== 0;
		});

		return $commits;
	}

	protected function addNewFilesToSvn($svn, $git)
	{
		$svnStatus = $svn->status();

		foreach ($svnStatus as $line) {
			if (! preg_match('/^\?\s+(?<path>.+)$/', $line, $matches)) {
				continue;
			}

			$this->addFileToSvn($svn, $matches['path'], $git);
		}
	}

	protected function addFileToSvn($svn, $path, $git)
	{
		if ($git->isPathIgnored($path)) {
			return;
		}

		// using @ when adding a file in svn specifies the peg revision, we need to add an extra @ at the end of the file
		// names containing @ for them to be added correctly
		if (strpos($path, '@') !== false) {
			$path .= '@';
		}

		$svn->add([ 'non-recursive' => null, $path ]);

		if (is_dir($path)) {
			foreach (array_merge(glob("$path/*"), glob("$path/.*")) as $path) {
				if (basename($path) == '.' || basename($path) == '..') {
					continue;
				}

				if (is_dir($path)) {
					$path = rtrim($path, '/') . '/';
				}

				$this->addFileToSvn($svn, $path, $git);
			}
		}
	}

	protected function removeDeletedFilesFromSvn()
	{
		exec('svn status | grep ^! | awk \'{print " --force "$2"@"}\' | xargs svn rm');
	}

	protected function saveMetadata($metadata, $git, $svn)
	{
		$metadata->set('lastPushedRevision', $git->getLastCommitHash());
		$metadata->set('lastPushedRemoteRevision', $svn->getCurrentRevision());

		$metadata->save();
	}

	protected function deployOnPushTargets($metadata, $svn, $output)
	{
		$targets = new TargetsRepository($metadata);

		$onPushTargets = array_filter($targets->all(), function($target)
		{
			return $target->deployOnPush;
		});

		if (! count($onPushTargets)) {
			return;
		}

		$output->writeln("<info>Deploying...</info>");
		$output->writeln('');

		foreach ($onPushTargets as $target) {
			$output->write("Deploying target \"{$target->name}\"... ");

			$target->deploy($svn->getUserName(), $svn->getPassword());

			$output->writeln('✓');
		}
	}
}
