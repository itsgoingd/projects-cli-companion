<?php namespace ProjectsCliCompanion\Git;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Gitignore
{
	protected $repositoryPath;

	protected $ignoredPaths = [];
	protected $excludedPaths = [];

	protected $defaultIgnoredPaths = [ '.git', '.svn' ];

	public function __construct($repositoryPath)
	{
		$this->repositoryPath = $repositoryPath;
	}

	public static function loadFromPath($repositoryPath)
	{
		$gitignore = new static($repositoryPath);
		$gitignore->load();

		return $gitignore;
	}

	public function isIgnored($path)
	{
		$path = rtrim($path, '/');

		foreach ($this->defaultIgnoredPaths as $ignoredPath) {
			if ($path === $ignoredPath) {
				return true;
			}
		}

		foreach ($this->excludedPaths as $excludedPath) {
			if (preg_match("#{$ignoredPath}#", $path)) {
				return false;
			}
		}

		foreach ($this->ignoredPaths as $ignoredPath) {
			if (preg_match("#{$ignoredPath}#", $path)) {
				return true;
			}
		}

		return false;
	}

	public function load()
	{
		$this->ignoredPaths = [];

		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->repositoryPath));

		foreach ($iterator as $file) {
			if ($file->getFilename() == '.gitignore') {
				$this->loadFile($file->getPathname(), $iterator->getSubPath());
			}
		}
	}

	public function loadFile($filePath, $currentRootPath = '')
	{
		$fileHandle = fopen($filePath, 'r');

		while ($line = fgets($fileHandle)) {
			$this->add($line, $currentRootPath);
		}

		fclose($fileHandle);
	}

	public function add($pattern, $currentRootPath = '')
	{
		$pattern = trim($pattern);

		if (strlen($pattern) == 0 || $pattern[0] == '#') {
			return;
		}

		// strip comment
		$previousCharacter = null;
		foreach (str_split($pattern) as $index => $character) {
			if ($character == '#' && $previousCharacter != '\\') {
				$pattern = substr($pattern, 0, $index - 1);
				break;
			}

			$previousCharacter = $character;
		}

		$pattern = str_replace([ '.', '**', '*', '?' ], [ '\.', '.*?', '[^/]*?', '.' ], $pattern);
		$pattern = ltrim($pattern, '/');

		if ($pattern[0] == '!') {
			$this->excludedPaths[] = ltrim($currentRootPath . '/' . substr($pattern, 1), '/');
		} else {
			$this->ignoredPaths[] = ltrim($currentRootPath . '/' . $pattern, '/');
		}
	}
}
