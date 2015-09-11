<?php namespace ProjectsCliCompanion\Svn;

class Svn
{
	protected $username;
	protected $password;

	public function __construct($username = null, $password = null)
	{
		$this->username = $username;
		$this->password = $password;
	}

	public function __call($name, $arguments)
	{
		return call_user_func_array([ $this, 'execute' ], array_merge([ $name ], $arguments));
	}

	public function execute($command, $arguments = [])
	{
		$commandLine = "svn $command";

		if ($this->username) {
			$arguments['username'] = $this->username;
		}

		if ($this->password) {
			$arguments['password'] = $this->password;
		}

		foreach ($arguments as $key => $argument) {
			$commandLine .= ' ';

			if (is_string($key)) {
				$commandLine .= $argument === null ? "--{$key}" : "--{$key}=";
			}

			if ($argument !== null) {
				$commandLine .= escapeshellarg($argument);
			}
		}

		$commandLine .= ' 2>&1';

		exec($commandLine, $output);

		return $output;
	}
}
