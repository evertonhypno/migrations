#!/usr/bin/env php
<?php

/* @var $loader Composer\Autoload\ClassLoader */
$loader = require_once 'vendor/autoload.php';

$loader->add('Migration', 'src');

$app = new \Symfony\Component\Console\Application('migrations');

$app->add(new \Migration\Command\Migrate());

$app->run();