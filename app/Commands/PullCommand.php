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
				'Projects username.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$svn = $this->getSvn($this->config, $input, $output);
		$git = $this->getGit();

		$output->write('<info>Retrieving remote changes... </info>');

		$svnLog = $svn->getLog('BASE');
		array_shift($svnLog);

		$output->writeln('<info>✓ (' . count($svnLog) . ' commits)</info>');
		$output->writeln('');

		foreach ($svnLog as $commit) {
			$output->write("Importing commit {$commit['revision']}... ");
			$output->write('downloading... ');

			$svn->up([ 'revision' => $commit['revision'], 'accept' => 'postpone' ]);

			$output->write('committing... ');

			$git->add([ '.' ]);

			$message = "[IMPORT] [{$commit['author']}] {$commit['message']} (" . date('d.m.Y H:i', $commit['date']) . ')';

			$git->commit([ 'message' => $message ]);

			$output->writeln('✓');
		}

		$this->reportMergeConflicts($svn, $output);
	}

	protected function reportMergeConflicts($svn, $output)
	{
		if (! preg_match_all('/^C\s+(?<filename>.+)$/m', implode("\n", $svn->status()), $conflicts)) {
			return;
		}

		$output->writeln('<error>Following merge conflicts need to be resolved:</error>');

		foreach ($conflicts['filename'] as $fileName) {
			$output->writeln("\t{$fileName}");
		}
	}
}
