<?php namespace ProjectsCliCompanion\Config;

class Config
{
	protected $data = [];

	public function __construct(array $data = [])
	{
		$this->data = $data;
	}

	public static function load()
	{
		if (! $data = json_decode(file_get_contents(getenv('HOME') . '/.projectsCliCompanion'), true)) {
			$data = [];
		}

		return new self($data);
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
		file_put_contents(getenv('HOME') . '/.projectsCliCompanion', json_encode($this->data));
	}
}