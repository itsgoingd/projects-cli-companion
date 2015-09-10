<?php namespace ProjectsCliCompanion\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckoutCommand extends BaseCommand
{
	protected function configure()
	{
		$this
			->setName('checkout')
			->setDescription('Checkout an existing project.')
			->addArgument(
				'projectName',
				InputArgument::REQUIRED,
				'Project name (eg. sandbox).'
			)
			->addArgument(
				'repositoryName',
				InputArgument::OPTIONAL,
				'Repository name (eg. api), defaults to code.'
			)
			->addOption(
				'path',
				'p',
				InputOption::VALUE_OPTIONAL,
				'Destination path, default to current directory.'
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
		$projectName = $input->getArgument('projectName');
		$repositoryName = $input->getArgument('repositoryName') ?: 'code';

		$repositoryUrl = "svn://projects.kbs-development.com/1/{$projectName}/svn/{$repositoryName}";

		$destinationPath = $input->getOption('path') ?: '.';

		$svn = $this->getSvn($this->config, $input, $output);

		$output->writeln('Downloading files...');

		$svn->checkout([ $repositoryUrl, $destinationPath ]);

		$output->writeln('Initializing local repository...');

		$this->createLocalGitRepository($destinationPath);

		$this->saveMetadata($destinationPath, $projectName);

		$output->writeln('Done!');
	}

	protected function createLocalGitRepository($destinationPath)
	{
		$cmd = 'git init ' . escapeshellarg($destinationPath);
		exec($cmd);

		$this->createDefaultGitignore($destinationPath);

		$cmd = 'git add .';
		exec($cmd);

		$cmd = 'git commit --message "Hello world"';
		exec($cmd);
	}

	protected function createDefaultGitignore($destinationPath)
	{
		if ($destinationPath[0] != '/') {
			$destinationPath = getcwd() . "/{$destinationPath}";
		}

		if (file_exists("{$destinationPath}/.gitignore")) {
			$gitignore = file_get_contents("{$destinationPath}/.gitignore");
		} else {
			$gitignore = '';
		}

		$gitignore .= "\n.svn\n";

		file_put_contents("{$destinationPath}/.gitignore", $gitignore);
	}

	protected function saveMetadata($destinationPath, $projectName)
	{
		exec('git rev-parse HEAD', $currentGitRevision);

		$metadata = [
			'lastCommitedRevision' => $currentGitRevision[0],
			'projectName' => $projectName
		];

		if ($destinationPath[0] != '/') {
			$destinationPath = getcwd() . "/{$destinationPath}";
		}

		file_put_contents("/{$destinationPath}/.svn/.projectsCliCompanion", json_encode($metadata));
	}
}