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
			->setDescription('Checkout a project from the remote server.')
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
				'serverName',
				's',
				InputOption::VALUE_OPTIONAL,
				'Projects server name.'
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
		$serverName = $input->getOption('serverName') ?: $this->config->get('serverName');
		$projectName = $input->getArgument('projectName');
		$repositoryName = $input->getArgument('repositoryName') ?: 'code';

		$repositoryUrl = "svn://$serverName/1/{$projectName}/svn/{$repositoryName}";

		$destinationPath = $input->getOption('path') ?: '.';

		$svn = $this->getSvn($this->config, $input, $output);
		$git = $this->getGit();

		$output->write('<info>Downloading files... </info>');

		$svnOutput = $svn->checkout([ $repositoryUrl, $destinationPath ]);

		if (isset($svnOutput[0]) && strpos($svnOutput[0], 'svn: E670008:') === 0) {
			$output->writeln('<error>Unable to connect to the svn repository, please check if the specified server, project and repository names are correct.</error>');
			$output->writeln("<error> - server name: {$serverName}</error>");
			$output->writeln("<error> - project name: {$projectName}</error>");
			$output->writeln("<error> - repository name: {$repositoryName}</error>");
			return;
		}

		$output->writeln('<info>✓</info>');

		$output->write('<info>Initializing repository... </info>');

		$this->createLocalGitRepository($git, $destinationPath);

		$output->writeln('<info>✓</info>');

		$this->saveMetadata($git, $svn, $destinationPath, $projectName);

		$output->writeln('<info>Enjoy working on your new project!</info>');
	}

	protected function createLocalGitRepository($git, $destinationPath)
	{
		$git->init([ $destinationPath ]);

		$this->createDefaultGitignore($destinationPath);

		$git->add([ '.' ]);

		$git->commit([ 'message' => 'Hello world' ]);
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

	protected function saveMetadata($git, $svn, $destinationPath, $projectName)
	{
		$metadata = [
			'lastPushedRevision' => $git->getLastCommitHash(),
			'lastPushedRemoteRevision' => $svn->getCurrentRevision(),
			'projectName' => $projectName
		];

		if ($destinationPath[0] != '/') {
			$destinationPath = getcwd() . "/{$destinationPath}";
		}

		file_put_contents("/{$destinationPath}/.svn/.projectsCliCompanion", json_encode($metadata));
	}
}
