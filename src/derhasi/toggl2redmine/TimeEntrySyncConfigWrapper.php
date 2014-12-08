<?php

namespace derhasi\toggl2redmine;

use derhasi\toggl2redmine\Config\TimeEntrySyncConfiguration;
use derhasi\toggl2redmine\Config\YamlConfigLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ConfigWrapper
 * @package derhasi\toggl2redmine
 */
class TimeEntrySyncConfigWrapper {

  const FILENAME = 'toggl2redmine.yml';

  /**
   * @var array
   */
  protected $processedConfiguration;

  /**
   * Constructor.
   *
   * @param string $root
   *   Root level key for the confiuration subtree.
   */
  public function __construct() {
  }

  /**
   * Get value from configuration file.
   *
   * @param string $name
   * @return mixed
   */
  public function getValueFromConfig($name) {
    if (!isset($this->processedConfiguration)) {
      $this->loadConfig();
    }

    if (isset($this->processedConfiguration[$name])) {
      return $this->processedConfiguration[$name];
    }
  }

  /**
   * Loads values from the actual config file.
   *
   * @see http://blog.servergrove.com/2014/02/21/symfony2-components-overview-config/
   */
  protected function loadConfig() {

    $configDirectories = array(
      $_SERVER['HOME'] . '/.toggl2redmine',
    );

    $locator = new FileLocator($configDirectories);
    $loader = new YamlConfigLoader($locator);

    // In the case we do not find the config file, we want to silently fail, as
    // we will have a fallback.
    try {
      $location = $locator->locate(static::FILENAME, null, true);
    }
    catch (\Exception $e) {
      // No file found, means no configuration.
      $this->processedConfiguration = array();
      return;
    }

    // Load values.
    $configValues = $loader->load($location);

    // process the array using the defined configuration
    $processor = new Processor();
    $configuration = new TimeEntrySyncConfiguration();

    $this->processedConfiguration = $processor->processConfiguration(
      $configuration,
      $configValues
    );
  }

} 