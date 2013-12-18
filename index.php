#!/usr/bin/env php
<?php

Phar::interceptFileFuncs();
set_include_path('phar://' . __FILE__ . PATH_SEPARATOR . get_include_path());

require_once 'vendor/autoload.php';
require_once 'Chefoeb.php';

$args = $argv;
array_shift($args);
$app = new Chefoeb();
$app->run($args);

__HALT_COMPILER(); ?>