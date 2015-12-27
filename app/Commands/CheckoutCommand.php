<?php namespace ProjectsCliCompanion\Commands;

use ProjectsCliCompanion\Metadata\Metadata;

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
				'Projects username.'
			)
			->addOption(
				'without-history',
				null,
				InputOption::VALUE_NONE,
				'Disables importing of the full revisions history, imports only the latest revision.'
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

		$fullHistory = ! $input->getOption('without-history');
		$latestRemoteRevision = $svn->getLatestRevision();

		$svnOutput = $svn->ls(); // sue svn ls to check connection

		if (isset($svnOutput[0]) && strpos($svnOutput[0], 'svn: E670008:') === 0) {
			$output->writeln('<error>Unable to connect to the svn repository, please check if the specified server, project and repository names are correct.</error>');
			$output->writeln("<error> - server name: {$serverName}</error>");
			$output->writeln("<error> - project name: {$projectName}</error>");
			$output->writeln("<error> - repository name: {$repositoryName}</error>");
			return;
		}

		$this->createLocalGitRepository($git, $destinationPath);

		$output->write('<info>Pulling files... </info>');

		if ($fullHistory) {
			$output->writeln('');
			$output->writeln('');

			$svn->checkout([ 'revision' => 1, $repositoryUrl, $destinationPath ]);

			$revisions = $svn->getLog();
			$revisionsCount = count($revisions);

			foreach ($revisions as $revision) {
				$output->write("Pulling revision {$revision['revision']}/{$revisionsCount}... downloading... ");

				$svn->up([ 'revision' => $revision['revision'] ]);

				$output->write('committing... ');

				$git->add([ '.' ]);

				$message = "[IMPORT] [{$revision['author']}] {$revision['message']} (" . date('d.m.Y H:i', $revision['date']) . ')';

				$git->commit([ 'message' => $message ]);

				$output->writeln('✓');
			}
		} else {
			$svn->checkout([ $repositoryUrl, $destinationPath ]);

			$git->add([ '.' ]);

			$git->commit([ 'message' => 'Initial import.' ]);

			$output->writeln('<info>✓</info>');
		}

		$this->createDefaultGitignore($git, $destinationPath);

		$this->saveMetadata($git, $svn, $destinationPath, $projectName);

		$output->writeln('');
		$output->writeln('<info>Good luck, have fun!</info>');
	}

	protected function createLocalGitRepository($git, $destinationPath)
	{
		$git->init([ $destinationPath ]);
	}

	protected function createDefaultGitignore($git, $destinationPath)
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

		$git->add([ '.gitignore' ]);

		$git->commit([ 'message' => 'Updated gitignore.' ]);
	}

	protected function saveMetadata($git, $svn, $destinationPath, $projectName)
	{
		if ($destinationPath[0] != '/') {
			$destinationPath = getcwd() . "/{$destinationPath}";
		}

		$metadata = Metadata::loadFromPath($destinationPath);

		$metadata->set([
			'lastPushedRevision'       => $git->getLastCommitHash(),
			'lastPushedRemoteRevision' => $svn->getCurrentRevision(),
			'projectName'              => $projectName,
			'deploymentTargets'        => []
		]);

		$metadata->save();
	}
}
