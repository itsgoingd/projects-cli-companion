<?php namespace ProjectsCliCompanion\Commands;

use ProjectsCliCompanion\Deployment\TargetsRepository;
use ProjectsCliCompanion\Metadata\Metadata;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployListCommand extends BaseCommand
{
	protected function configure()
	{
		$this
			->setName('deploy:list')
			->setDescription('List configured deployment targets.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$targets = new TargetsRepository(Metadata::loadFromPath(getcwd()));

		$rows = [];

		foreach ($targets->all() as $target) {
			$rows[] = [
				$target->name,
				$target->hostName,
				$target->userName,
				$target->path,
				$target->environment,
				$target->deployOnPush ? 'yes' : 'no'
			];
		}

		$table = new Table($output);
        $table
            ->setHeaders([ 'Name', 'Hostname', 'Username', 'Path', 'Environment', 'Deploy on push'])
            ->setRows($rows);
        $table->render();
	}
}
