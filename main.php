<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;

$app = new Application();

$app->add(new ProjectsCliCompanion\CheckoutCommand);
$app->add(new ProjectsCliCompanion\PullCommand);
$app->add(new ProjectsCliCompanion\PushCommand);

$app->run();
