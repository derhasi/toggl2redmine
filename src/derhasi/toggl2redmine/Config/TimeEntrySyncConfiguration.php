<?php

namespace derhasi\toggl2redmine\Config;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class TimeEntrySyncConfiguration implements ConfigurationInterface
{
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder();
    $rootNode = $treeBuilder->root('time-entry-sync');

    $rootNode
      ->children()
        ->scalarNode('redmineURL')
        ->end()
        ->scalarNode('redmineAPIKey')
        ->end()
        ->scalarNode('togglAPIKey')
        ->end()
        ->scalarNode('fromDate')
        ->end()
        ->scalarNode('toDate')
        ->end()
        ->scalarNode('defaultActivity')
        ->end()
        ->scalarNode('workspace')
        ->end()
      ->end()
    ;

    return $treeBuilder;
  }
}