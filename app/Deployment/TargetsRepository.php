<?php namespace ProjectsCliCompanion\Deployment;

use ProjectsCliCompanion\Metadata\Metadata;

class TargetsRepository
{
    protected $metadata;

    public function __construct(Metadata $metadata)
    {
        $this->metadata = $metadata;
    }

    public function add($name, $hostName, $userName, $path, $environment, $deployOnPush)
    {
        $deploymentTargets = $this->metadata->get('deploymentTargets', []);

        $deploymentTargets[] = compact('name', 'hostName', 'userName', 'path', 'environment', 'deployOnPush');

        $this->metadata->set('deploymentTargets', $deploymentTargets);
        $this->metadata->save();
    }

    public function all()
    {
        $targets = [];

        foreach ($this->metadata->get('deploymentTargets', []) as $targetData) {
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
        $deploymentTargets = $this->metadata->get('deploymentTargets', []);

        $deploymentTargets = array_filter($deploymentTargets, function($targetData) use($name)
        {
            return $targetData['name'] != $name;
        });

        $this->metadata->set('deploymentTargets', $deploymentTargets);
        $this->metadata->save();
    }
}
