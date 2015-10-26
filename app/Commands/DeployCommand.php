<?php namespace ProjectsCliCompanion\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use ProjectsCliCompanion\Deployment\TargetsRepository;

class DeployCommand extends BaseCommand
{
	protected function configure()
	{
		$this
			->setName('deploy')
			->setDescription('Deploy latest revision to the specified target.')
			->addArgument(
				'target',
				InputArgument::REQUIRED,
				'Target name.'
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

		$targets = new TargetsRepository(getcwd() . '/.svn/.projectsCliCompanion');

		$name = $input->getArgument('target');

		if (! $target = $targets->find($name)) {
			$output->writeln("<error>Target \"{$name}\" not found.</error>");
			return;
		}

		$target->deploy($svn->getUserName(), $svn->getPassword());

		$output->writeln("<info>Target \"{$name}\" successfully deployed.</info>");
	}
}
