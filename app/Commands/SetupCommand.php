<?php namespace ProjectsCliCompanion\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetupCommand extends BaseCommand
{
	protected function configure()
	{
		$this
			->setName('setup')
			->setDescription('Set up global projects app configuration.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln('This setup wizard will take you step by step through configuration of the Projects CLI Companion app.');
		$output->writeln('Configuration is stored in "~/.projectsCliCompanion".');
		$output->writeln('');

		$username = $this->getHelper('dialog')->ask($output, "Please enter your username (optional):\n");

		$password = $this->getHelper('dialog')->askHiddenResponse($output, "Please enter your password (optional, note that password is stored in plaintext in the configuration file):\n");

		$metadata = compact('username', 'password');

		file_put_contents(getenv('HOME') . '/.projectsCliCompanion', json_encode($metadata));

		$output->writeln('Done!');
	}
}
