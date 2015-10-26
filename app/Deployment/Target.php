<?php namespace ProjectsCliCompanion\Deployment;

class Target
{
    public $name;
    public $hostName;
    public $userName;
    public $path;
    public $deployOnPush;

    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->hostName = $data['hostName'];
        $this->userName = $data['userName'];
        $this->path = $data['path'];
        $this->deployOnPush = $data['deployOnPush'];
    }

    public function deploy($svnUserName, $svnPassword)
    {
        $remoteCommandLine = [
            'cd ' . escapeshellarg($this->path),
            'svn --no-auth-cache --username ' . escapeshellarg($svnUserName) . ' --password ' . escapeshellarg($svnPassword) . ' up',
            'composer install',
            'bower install',
            'php artisan migrate'
        ];

        $remoteCommandLine = implode('; ', $remoteCommandLine);

        exec('ssh ' . escapeshellarg("{$this->userName}@{$this->hostName}") . ' "' . $remoteCommandLine . '" 2>&1');
    }
}
