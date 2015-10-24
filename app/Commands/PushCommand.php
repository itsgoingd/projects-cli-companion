<?php namespace ProjectsCliCompanion\Commands;

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
				'Projects username, defaults to current system username.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$svn = $this->getSvn($this->config, $input, $output);
		$git = $this->getGit();

		if ($svn->getCurrentRevision() != $svn->getLatestRevision()) {
			$output->writeln('<error>Please use `projects pull` to pull in new remote changes first.</error>');

			return;
		}

		$output->write('<info>Retrieving changes to push... </info>');

		$commitsToPush = $this->getNotPushedCommits($git);

		$output->writeln('<info>✓ (' . count($commitsToPush) . ' commits)</info>');
		$output->writeln('');

		$gitignore = $this->loadGitignore();

		if ($svn->getCurrentRevision() == $this->getLastPushedRemoteRevision()) {
			$this->pushAll($commitsToPush, $gitignore, $svn, $git, $input, $output);
		} else {
			$this->pushMerged($commitsToPush, $gitignore, $svn, $git, $input, $output);
		}

		$web = $this->getWeb($this->config, $input, $output);

		$this->postTicketsCommentsForCommits($web, $commitsToPush);

		$svn->up();

		$this->saveMetadata($git, $svn);
	}

	protected function pushAll($commitsToPush, $gitignore, $svn, $git, $input, $output)
	{
		foreach ($commitsToPush as $i => $commit) {
			$output->write("Pushing commit {$commit['shortRevision']}... ");
			$output->write('checking out... ');

			$git->checkout([ $commit['revision'] ]);

			$output->write('committing... ');

			if ($i == count($commitsToPush) - 1) {
				$message = $input->getArgument('workTime') . ' ' . $commit['message'];
			}

			$this->addNewFilesToSvn($svn, $gitignore);
			$this->removeDeletedFilesFromSvn();

			$svn->commit([ '.', 'message' => $commit['message'] ]);

			$output->writeln('✓');
		}

		$git->checkout([ 'master' ]);
	}

	protected function pushMerged($commitsToPush, $gitignore, $svn, $git, $input, $output)
	{
		$message = $input->getArgument('workTime') . ' ';

		foreach ($commitsToPush as $i => $commit) {
			$message .= "{$commit['message']}\n";
		}

		$output->write("Pushing commits... committing... ");

		$this->addNewFilesToSvn($svn, $gitignore);
		$this->removeDeletedFilesFromSvn();

		$svn->commit([ '.', 'message' => $message ]);

		$output->writeln('✓');
	}

	protected function getNotPushedCommits($git)
	{
		$metadata = json_decode(file_get_contents(getcwd() . '/.svn/.projectsCliCompanion'), true);

		$gitLog = $git->log();

		$commits = [];
		$commit = [];

		foreach ($gitLog as $line) {
			if (preg_match('/^commit (?<revision>.+)$/', $line, $matches)) {
				$commits[] = $commit;

				if ($matches['revision'] == $metadata['lastPushedRevision']) {
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

	protected function addNewFilesToSvn($svn, $gitignore)
	{
		$svnStatus = $svn->status();

		foreach ($svnStatus as $line) {
			if (! preg_match('/^\?\s+(?<path>.+)$/', $line, $matches)) {
				continue;
			}

			$this->addFileToSvn($svn, $matches['path'], $gitignore);
		}
	}

	protected function addFileToSvn($svn, $path, $gitignore)
	{
		if ($this->isPathIgnored($path, $gitignore)) {
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

				$this->addFileToSvn($svn, $path, $gitignore);
			}
		}
	}

	protected function removeDeletedFilesFromSvn()
	{
		exec('svn status | grep ^! | awk \'{print " --force "$2}\' | xargs svn rm');
	}

	protected function loadGitignore()
	{
		$ignoredPaths = [];

		$lines = explode("\n", file_get_contents(getcwd() . '/.gitignore'));

		foreach ($lines as $line) {
			if (trim($line) != '' && $line[0] != '#') {
				$ignoredPaths[] = $line;
			}
		}

		return $ignoredPaths;
	}

	protected function isPathIgnored($path, $gitignore)
	{
		foreach ($gitignore as $ignoredPath) {
			if ($path == $ignoredPath) {
				return true;
			}

			$regex = '#^' . str_replace([ '*', '#' ], [ '.*?', '\#' ], $ignoredPath) . '$#';

			if (preg_match($regex, $path)) {
				return true;
			}
		}

		if ($path == '.git' || $path == '.gitignore') {
			return true;
		}
	}

	protected function saveMetadata($git, $svn)
	{
		$metadata = json_decode(file_get_contents(getcwd() . '/.svn/.projectsCliCompanion'), true);

		$metadata['lastPushedRevision'] = $git->getLastCommitHash();
		$metadata['lastPushedRemoteRevision'] = $svn->getCurrentRevision();

		file_put_contents(getcwd() . '/.svn/.projectsCliCompanion', json_encode($metadata));
	}

	protected function getLastPushedRemoteRevision()
	{
		$metadata = json_decode(file_get_contents(getcwd() . '/.svn/.projectsCliCompanion'), true);

		return $metadata['lastPushedRemoteRevision'];
	}

	protected function postTicketsCommentsForCommits($web, $commits)
	{
		$comments = [];

		foreach ($commits as $commit) {
			if (! preg_match('/(?<keyword>[A-Za-z]+)?\s*#(?<ticketId>\d+)/', $commit['message'], $matches)) {
				continue;
			}

			$ticketId = $matches['ticketId'];

			if (! isset($comments[$ticketId])) {
				$comments[$ticketId] = [ 'message' => '', 'status' => null ];
			}

			if ($matches['keyword'] == 'done' || $matches['keyword'] == 'fixed' || $matches['keyword'] == 'fixes') {
				$comments[$ticketId]['status'] = 'done';
			}

			$comments[$ticketId]['message'] .= '- ' . $commit['message'] . "\n";
		}

		$metadata = json_decode(file_get_contents(getcwd() . '/.svn/.projectsCliCompanion'), true);

		foreach ($comments as $ticketId => $comment) {
			$web->postTicketComment($metadata['projectName'], $ticketId, '', $comment['message'], $comment['status']);
		}
	}
}
