<?php

namespace derhasi\toggl2redmine\Config;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;

class YamlConfigLoader extends FileLoader
{
  public function load($resource, $type = null)
  {
    $configValues = Yaml::parse(file_get_contents($resource));

    return $configValues;
  }

  public function supports($resource, $type = null)
  {
    return is_string($resource) && 'yml' === pathinfo(
      $resource,
      PATHINFO_EXTENSION
    );
  }
}
