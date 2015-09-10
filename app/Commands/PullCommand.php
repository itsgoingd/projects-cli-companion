<?php namespace ProjectsCliCompanion\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PullCommand extends BaseCommand
{
	protected function configure()
	{
		$this
			->setName('pull')
			->setDescription('Pull in remote changes.')
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

		$output->write('Retrieving SVN log...');

		$svnLog = $svn->log([ 'revision' => 'BASE:HEAD' ]);

		$output->writeln('✓');

		$output->write('Parsing SVN log...');

		$svnLog = $this->parseSvnLog($svnLog);

		$output->writeln('✓ (' . count($svnLog) . ' commits)');
		$output->writeln('');

		foreach ($svnLog as $commit) {
			$output->write("Importing commit {$commit['revision']}... ");
			$output->write('updating from svn... ');

			$svn->up([ 'r' => $commit['revision'] ]);

			$output->write('commiting to git... ');

			exec('git add .');

			$message = "[IMPORT] [{$commit['author']}] {$commit['message']} (" . date('d.m.Y H:i', $commit['date']) . ')';

			exec('git commit . --message ' . escapeshellarg($message));

			$output->writeln('✓');
		}

		$this->saveMetadata();
	}

	protected function parseSvnLog($input)
	{
		array_shift($input);

		$log = [];

		foreach ($input as $line) {
			if ($line == '------------------------------------------------------------------------') {
				$log[] = $item;
			} elseif (preg_match('/^r(?<revision>\d+) \| (?<author>.+?) \| (?<date>.+?) \(.+?\) \| \d+ line(s)?$/', $line, $matches)) {
				$item = [
					'revision' => $matches['revision'],
					'author'   => $matches['author'],
					'date'     => strtotime($matches['date']),
					'message'  => ''
				];
			} elseif ($line != '') {
				$item['message'] .= $line . "\n";
			}
		}

		array_shift($log);

		return $log;
	}

	protected function saveMetadata()
	{
		exec('git rev-parse HEAD', $currentGitRevision);

		$metadata = json_decode(file_get_contents(getcwd() . '/.svn/.projectsCliCompanion'), true);

		$metadata['lastCommitedRevision'] = $currentGitRevision[0];

		file_put_contents(getcwd() . '/.svn/.projectsCliCompanion', json_encode($metadata));
	}
}
