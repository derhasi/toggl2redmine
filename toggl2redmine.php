<?php

error_reporting(E_ALL & E_STRICT);

// Handling autoloading for different use cases.
// @see https://github.com/sebastianbergmann/phpunit/blob/master/phpunit
foreach (array(__DIR__ . '/../../autoload.php', __DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php') as $file) {
  if (file_exists($file)) {
    define('TOGGL2REDMINE_COMPOSER_AUTOLOAD', $file);
    break;
  }
}

unset($file);

// Provide warning, when no autoloader was found.
if (!defined('TOGGL2REDMINE_COMPOSER_AUTOLOAD')) {
  fwrite(STDERR,
    'You need to set up the project dependencies using the following commands:' . PHP_EOL .
    'wget http://getcomposer.org/composer.phar' . PHP_EOL .
    'php composer.phar install' . PHP_EOL
  );
  die(1);
}

require TOGGL2REDMINE_COMPOSER_AUTOLOAD;

use \derhasi\toggl2redmine\Command\TimeEntrySync;
use \Symfony\Component\Console\Application;


$application = new Application();
$application->add(new TimeEntrySync());
$application->run();

