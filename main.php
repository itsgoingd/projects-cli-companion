<?php

require __DIR__ . '/vendor/autoload.php';

use ProjectsCliCompanion\Commands;

use Symfony\Component\Console\Application;

$app = new Application();

$app->add(new Commands\CheckoutCommand);
$app->add(new Commands\PullCommand);
$app->add(new Commands\PushCommand);

$app->run();
