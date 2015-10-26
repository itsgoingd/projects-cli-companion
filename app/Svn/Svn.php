<?php namespace ProjectsCliCompanion\Svn;

class Svn
{
	protected $userName;
	protected $password;

	public function __construct($userName = null, $password = null)
	{
		$this->userName = $userName;
		$this->password = $password;
	}

	public function getUserName()
	{
		return $this->userName;
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function __call($name, $arguments)
	{
		return call_user_func_array([ $this, 'execute' ], array_merge([ $name ], $arguments));
	}

	public function getCurrentRevision()
	{
		if (preg_match('/^Revision: (?<revision>\d+)/m', implode("\n", $this->info()), $matches)) {
			return $matches['revision'];
		}
	}

	public function getLatestRevision()
	{
		if (preg_match('/^Revision: (?<revision>\d+)/m', implode("\n", $this->info([ 'revision' => 'HEAD' ])), $matches)) {
			return $matches['revision'];
		}
	}

	public function getLog($startRevision = 1, $endRevision = 'HEAD')
	{
		return $this->parseSvnLog($this->log([ 'revision' => "{$startRevision}:{$endRevision}" ]));
	}

	public function execute($command, $arguments = [])
	{
		$commandLine = "svn $command";

		if ($this->userName) {
			$arguments['username'] = $this->userName;
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

	protected function parseSvnLog($input)
	{
		array_shift($input);

		$log = [];

		foreach ($input as $line) {
			if ($line == '------------------------------------------------------------------------') {
				$log[] = $item;
			} elseif (preg_match('/^r(?<revision>\d+) \| (?<author>.+?) \| (?<date>.+?) \(.+?\) \| \d+ line(s)?$/', $line, $matches)) {
				$item = [
					'revision' => $matches['revision'],
					'author'   => $matches['author'],
					'date'     => strtotime($matches['date']),
					'message'  => ''
				];
			} elseif ($line != '') {
				$item['message'] .= $line . "\n";
			}
		}

		return $log;
	}
}
