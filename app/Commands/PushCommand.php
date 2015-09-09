<?php namespace ProjectsCliCompanion\Commands;

use ProjectsCliCompanion\Svn\Svn;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PushCommand extends Command
{
	protected $svn;

	protected function configure()
	{
		$this
			->setName('push')
			->setDescription('Push out changes to remote repository.')
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
		$dialog = $this->getHelper('dialog');

		$username = $input->getOption('username');
		$password = $dialog->askHiddenResponse($output, 'Please enter your password:');

		$this->svn = new Svn($username, $password);

		$output->write('Retrieving GIT log... ');

		$commitsToPush = $this->getNotPushedCommits();

		$output->writeln('✓ (' . count($commitsToPush) . ' commits)');
		$output->writeln('');

		foreach ($commitsToPush as $i => $commit) {
			$output->write("Pushing commit {$commit['revision']}... ");
			$output->write('checking out of git... ');

			exec('git checkout ' . escapeshellarg($commit['revision']) . ' 2>&1');

			$output->write('commiting to svn... ');

			$message = $commit['message'];

			if ($i == count($commitsToPush) - 1) {
				$message = $input->getArgument('workTime') . ' ' . $message;
			}

			$this->addNewFilesToSvn();
			exec('svn status | grep ^! | awk \'{print " --force "$2}\' | xargs svn rm');

			$this->svn->commit([ '.', 'message' => $message ]);

			$output->writeln('✓');
		}

		$this->svn->up();

		exec('git checkout master 2>&1');
	}

	protected function getNotPushedCommits()
	{
		$metadata = json_decode(file_get_contents(getcwd() . '/.svn/.projectsCliCompanion'), true);

		exec('git log', $gitLog);

		$commits = [];
		$commit = [];

		foreach ($gitLog as $line) {
			if (preg_match('/^commit (?<revision>.+)$/', $line, $matches)) {
				$commits[] = $commit;

				if ($matches['revision'] == $metadata['lastCommitedRevision']) {
					break;
				}

				$commit = [
					'revision' => $matches['revision'],
					'message'  => ''
				];
			} elseif (strpos($line, 'Author:') !== 0 && strpos($line, 'Date:') !== 0 && trim($line) != '') {
				$commit['message'] .= $line . "\n";
			}
		}

		array_shift($commits);

		return array_reverse($commits);
	}

	protected function addNewFilesToSvn()
	{
		$gitignore = explode("\n", file_get_contents(getcwd() . '/.gitignore'));

		$svnStatus = $this->svn->status();

		foreach ($svnStatus as $line) {
			if (! preg_match('/^\?\s+(?<path>.+)$/', $line, $matches)) {
				continue;
			}

			$this->addFileToSvn($matches['path'], $gitignore);
		}
	}

	protected function addFileToSvn($path, $gitignore)
	{
		if ($this->isPathIgnored($path, $gitignore)) {
			return;
		}

		$this->svn->add([ 'non-recursive' => null, $path ]);

		if (is_dir($path)) {
			foreach (array_merge(glob("$path/*"), glob("$path/.*")) as $path) {
				if (basename($path) == '.' || basename($path) == '..') {
					continue;
				}

				$this->addFileToSvn($path, $gitignore);
			}
		}
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
}
