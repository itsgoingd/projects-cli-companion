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
			->setDescription('Pull in changes from remote server.')
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

		$output->write('<info>Retrieving remote changes... </info>');

		$svnLog = $svn->log([ 'revision' => 'BASE:HEAD' ]);

		$output->writeln('<info>✓</info>');

		$output->write('<info>Processing remote changes... </info>');

		$svnLog = $this->parseSvnLog($svnLog);

		$output->writeln('<info>✓ (' . count($svnLog) . ' commits)</info>');
		$output->writeln('');

		foreach ($svnLog as $commit) {
			$output->write("Importing commit {$commit['revision']}... ");
			$output->write('downloading files... ');

			$svn->up([ 'r' => $commit['revision'] ]);

			$output->write('committing... ');

			$git->add([ '.' ]);

			$message = "[IMPORT] [{$commit['author']}] {$commit['message']} (" . date('d.m.Y H:i', $commit['date']) . ')';

			$git->commit([ 'message' => $message ]);

			$output->writeln('✓');
		}

		$this->saveMetadata($git);
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

	protected function saveMetadata($git)
	{
		$metadata = json_decode(file_get_contents(getcwd() . '/.svn/.projectsCliCompanion'), true);

		$metadata['lastCommitedRevision'] = $git->getLastCommitHash();

		file_put_contents(getcwd() . '/.svn/.projectsCliCompanion', json_encode($metadata));
	}
}
