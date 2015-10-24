<?php namespace ProjectsCliCompanion\Git;

class Git
{
	public function __call($name, $arguments)
	{
		return call_user_func_array([ $this, 'execute' ], array_merge([ $name ], $arguments));
	}

	public function getLastCommitHash()
	{
		return $this->execute('rev-parse', [ 'HEAD' ])[0];
	}

	public function getShortRevision($revision)
	{
		return $this->execute('rev-parse', [ 'short' => 7, $revision ])[0];
	}

	public function execute($command, $arguments = [])
	{
		$commandLine = "git $command";

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
