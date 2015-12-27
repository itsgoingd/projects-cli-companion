<?php namespace ProjectsCliCompanion\Metadata;

class Metadata
{
	const VERSION = 1;

	protected $projectPath;

	protected $data = [];

	public function __construct($projectPath)
	{
		$this->projectPath = $projectPath;

		if (! file_exists($this->projectPath . '/.svn/.projectsCliCompanion')) {
			$this->createDefault();
		}
	}

	public static function loadFromPath($projectPath)
	{
		$metadata = new self($projectPath);
		$metadata->load();

		return $metadata;
	}

	public function load()
	{
		if (! $data = json_decode(file_get_contents($this->projectPath . '/.svn/.projectsCliCompanion'), true)) {
			$data = [];
		}

		$this->data = $data;
	}

	public function get($key, $default = null)
	{
		return isset($this->data[$key]) ? $this->data[$key] : $default;
	}

	public function set($key, $value = null)
	{
		if (is_array($key)) {
			$this->data = array_merge($this->data, $key);
		} else {
			$this->data[$key] = $value;
		}
	}

	public function save()
	{
		file_put_contents($this->projectPath  . '/.svn/.projectsCliCompanion', json_encode($this->data));
	}

	protected function createDefault()
	{
		$this->data = [
			'version' => static::VERSION
		];

		$this->save();
	}
}
