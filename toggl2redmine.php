#!/usr/bin/env php
<?php

error_reporting(E_ALL & E_STRICT);

// This file is generated by Composer
require_once 'vendor/autoload.php';

use \derhasi\toggl2redmine\Command\TimeEntrySync;
use \Symfony\Component\Console\Application;


$application = new Application();
$application->add(new TimeEntrySync());
$application->run();

