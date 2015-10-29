<?php namespace ProjectsCliCompanion\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use ProjectsCliCompanion\Deployment\TargetsRepository;

class DeployAddCommand extends BaseCommand
{
	protected function configure()
	{
		$this
			->setName('deploy:add')
			->setDescription('Add a new deployment target.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$targets = new TargetsRepository(getcwd() . '/.svn/.projectsCliCompanion');

		$name = $this->getHelper('dialog')->askAndValidate($output, "Please enter the target name:\n", function($answer) use($targets)
		{
			if ($targets->find($answer) !== null) {
				throw new \RuntimeException("Target with the name \"{$answer}\" already exists.");
			}

			return $answer;
		});

		$hostName = $this->getHelper('dialog')->ask($output, "Hostname:\n");

		$userName = $this->getHelper('dialog')->ask($output, "Username:\n");

		$path = $this->getHelper('dialog')->ask($output, "Site root:\n");

		if (! $environment = $this->getHelper('dialog')->ask($output, "Environment: (development)\n")) {
			$environment = 'development';
		}

		$deployOnPush = $this->getHelper('dialog')->askConfirmation($output, "Deploy automatically on push? (no)\n");

		$targets->add($name, $hostName, $userName, $path, $environment, $deployOnPush);

		if ($this->getHelper('dialog')->askConfirmation($output, "Set up SSH public key authentification? (yes)\n")) {
			exec('ssh-copy-id ' . escapeshellarg("{$userName}@{$hostName}"));
		}

		$output->writeln("<info>New deployment target \"{$name}\" added successfully!</info>");
	}
}
