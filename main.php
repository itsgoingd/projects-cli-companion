<?php

require __DIR__ . '/vendor/autoload.php';

use ProjectsCliCompanion\Commands;
use ProjectsCliCompanion\Config\Config;

use Symfony\Component\Console\Application;

$app = new Application('Projects CLI companion', '1.0');

$config = Config::loadDefault();

$app->add(new Commands\CheckoutCommand($config));
$app->add(new Commands\DeployCommand($config));
$app->add(new Commands\DeployAddCommand($config));
$app->add(new Commands\DeployListCommand($config));
$app->add(new Commands\DeployRemoveCommand($config));
$app->add(new Commands\PullCommand($config));
$app->add(new Commands\PushCommand($config));
$app->add(new Commands\SetupCommand($config));

$app->run();
