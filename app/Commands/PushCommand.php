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
		$svn = $this->getSvn($this->config, $input, $output);

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

			$this->addNewFilesToSvn($svn);
			exec('svn status | grep ^! | awk \'{print " --force "$2}\' | xargs svn rm');

			$svn->commit([ '.', 'message' => $message ]);

			$output->writeln('✓');
		}

		$web = $this->getWeb($this->config, $input, $output);

		$this->postTicketsCommentsForCommits($web, $commitsToPush);

		$svn->up();

		$this->saveMetadata();

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
				$commit['message'] .= trim($line) . "\n";
			}
		}

		array_shift($commits);

		return array_reverse($commits);
	}

	protected function addNewFilesToSvn($svn)
	{
		$gitignore = $this->loadGitignore();

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

		$svn->add([ 'non-recursive' => null, $path ]);

		if (is_dir($path)) {
			foreach (array_merge(glob("$path/*"), glob("$path/.*")) as $path) {
				if (basename($path) == '.' || basename($path) == '..') {
					continue;
				}

				$this->addFileToSvn($svn, $path, $gitignore);
			}
		}
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

	protected function saveMetadata()
	{
		exec('git rev-parse HEAD', $currentGitRevision);

		$metadata = json_decode(file_get_contents(getcwd() . '/.svn/.projectsCliCompanion'), true);

		$metadata['lastCommitedRevision'] = $currentGitRevision[0];

		file_put_contents(getcwd() . '/.svn/.projectsCliCompanion', json_encode($metadata));
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
