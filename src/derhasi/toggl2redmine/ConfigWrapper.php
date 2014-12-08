<?php

namespace derhasi\toggl2redmine;

use derhasi\toggl2redmine\Config\Configuration;
use derhasi\toggl2redmine\Config\YamlConfigLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;

/**
 * Class ConfigWrapper
 * @package derhasi\toggl2redmine
 */
class ConfigWrapper {

  const FILENAME = 'toggl2redmine.yml';

  /**
   * @var array
   */
  protected $processedConfiguration;

  /**
   * @var string
   */
  protected $configRoot;

  /**
   * Constructor.
   *
   * @param string $root
   *   Root level key for the confiuration subtree.
   */
  public function __construct($root) {
    $this->configRoot = $root;
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

    if (isset($this->processedConfiguration[$this->configRoot][$name])) {
      return $this->processedConfiguration[$this->configRoot][$name];
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
      $configValues = $loader->load($locator->locate(static::FILENAME, null, false));
    }
    catch (\Exception $e) {
      // No file found, means no configuration.
      print $e->getMessage();
      $this->processedConfiguration = array();
      return;
    }

    // process the array using the defined configuration
    $processor = new Processor();
    $configuration = new Configuration();

    $this->processedConfiguration = $processor->processConfiguration(
      $configuration,
      $configValues
    );
  }

} 