<?php namespace ProjectsCliCompanion\Commands;

use ProjectsCliCompanion\Config\Config;
use ProjectsCliCompanion\Git\Git;
use ProjectsCliCompanion\Svn\Svn;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
	protected $config;

	public function __construct(Config $config)
	{
		$this->config = $config;

		parent::__construct();
	}

	protected function getGit()
	{
		return new Git();
	}

	protected function getSvn(Config $config, InputInterface $input, OutputInterface $output)
	{
		$username = $config->get('username', $input->getOption('username'));

		if (! $password = $config->getBinary('password')) {
			$password = $this->getHelper('dialog')->askHiddenResponse($output, 'Please enter your password:');
		}

		return new Svn($username, $password);
	}
}
