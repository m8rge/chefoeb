#!/usr/bin/env php
<?php

Phar::interceptFileFuncs();
set_include_path('phar://' . __FILE__ . PATH_SEPARATOR . get_include_path());

require_once 'ConsoleApp.php';
require_once 'Chefoeb.php';

$app = new Chefoeb();
$app->run(!empty($argv[1]) ? $argv[1] : null);

__HALT_COMPILER(); ?>