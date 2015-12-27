<?php namespace ProjectsCliCompanion\Commands;

use ProjectsCliCompanion\Deployment\TargetsRepository;
use ProjectsCliCompanion\Metadata\Metadata;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployRemoveCommand extends BaseCommand
{
	protected function configure()
	{
		$this
			->setName('deploy:remove')
			->setDescription('Remove a deployment target.')
			->addArgument(
				'target',
				InputArgument::REQUIRED,
				'Target name.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$targets = new TargetsRepository(Metadata::loadFromPath(getcwd()));

		$name = $input->getArgument('target');

		if ($targets->find($name) === null) {
			$output->writeln("<error>Target \"{$name}\" not found.</error>");
			return;
		}

		$targets->remove($name);

		$output->writeln("<info>Deployment target \"{$name}\" successfully removed.</info>");
	}
}
