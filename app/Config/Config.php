<?php namespace ProjectsCliCompanion\Config;

class Config
{
	const VERSION = 1;

	protected $configPath;

	protected $data = [];

	public function __construct($configPath)
	{
		$this->configPath = $configPath;

		if (! file_exists($configPath)) {
			$this->createDefault();
		}
	}

	public static function loadDefault()
	{
		$config = new self(getenv('HOME') . '/.projectsCliCompanion');
		$config->load();

		return $config;
	}

	public function load()
	{
		if (! $data = json_decode(file_get_contents($this->configPath), true)) {
			$data = [];
		}

		$this->data = $data;
	}

	public function get($key, $default = null)
	{
		return isset($this->data[$key]) ? $this->data[$key] : $default;
	}

	public function set($key, $value)
	{
		$this->data[$key] = $value;
	}

	public function save()
	{
		file_put_contents($this->configPath, json_encode($this->data));
	}

	protected function createDefault()
	{
		$this->data = [
			'version' => static::VERSION
		];

		$this->save();
	}
}
