#!/usr/bin/env php
<?php

use Composer\Autoload\ClassLoader;
use Hypnobox\Migration\Command\MigrateCommand;
use Symfony\Component\Console\Application;

/* @var $loader ClassLoader */
$loader = require_once 'vendor/autoload.php';

$app = new Application('migrations');

$app->add(new MigrateCommand());
$app->run();