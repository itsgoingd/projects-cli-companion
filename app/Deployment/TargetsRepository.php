<?php namespace ProjectsCliCompanion\Deployment;

class TargetsRepository
{
    protected $metadataPath;

    public function __construct($metadataPath)
    {
        if (! file_exists($metadataPath)) {
            throw new \Exception('Metadata file not found.');
        }

        $this->metadataPath = $metadataPath;
    }

    public function add($name, $hostName, $userName, $path, $environment, $deployOnPush)
    {
        $metadata = json_decode(file_get_contents($this->metadataPath), true);

        $metadata['deploymentTargets'][] = compact('name', 'hostName', 'userName', 'path', 'environment', 'deployOnPush');

        file_put_contents($this->metadataPath, json_encode($metadata));
    }

    public function all()
    {
        $metadata = json_decode(file_get_contents($this->metadataPath), true);

        $targets = [];

        foreach ($metadata['deploymentTargets'] as $targetData) {
            $targets[] = new Target($targetData);
        }

        return $targets;
    }

    public function find($name)
    {
        foreach ($this->all() as $target) {
            if ($target->name == $name) {
                return $target;
            }
        }
    }

    public function remove($name)
    {
        $metadata = json_decode(file_get_contents($this->metadataPath), true);

        $metadata['deploymentTargets'] = array_filter($metadata['deploymentTargets'], function($targetData) use($name)
        {
            return $targetData['name'] != $name;
        });

        file_put_contents($this->metadataPath, json_encode($metadata));
    }
}
