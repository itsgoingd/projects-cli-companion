<?php namespace ProjectsCliCompanion\Git;

class Git
{
	protected $lastReturnCode;

	public function __call($name, $arguments)
	{
		return call_user_func_array([ $this, 'execute' ], array_merge([ $name ], $arguments));
	}

	public function getLastReturnCode()
	{
		return $this->lastReturnCode;
	}

	public function getLastCommitHash()
	{
		return $this->execute('rev-parse', [ 'HEAD' ])[0];
	}

	public function getShortRevision($revision)
	{
		return $this->execute('rev-parse', [ 'short' => 7, $revision ])[0];
	}

	public function isPathIgnored($path)
	{
		$this->execute('check-ignore', [ 'q' => null, $path ]);

		return $this->getLastReturnCode();
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

		exec($commandLine, $output, $returnCode);

		$this->lastReturnCode = $returnCode;

		return $output;
	}
}
