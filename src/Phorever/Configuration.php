<?php
namespace Phorever;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('phorever');

        $rootNode
            ->children()
                ->scalarNode('pidfile')->cannotBeEmpty()->defaultValue('./phorever.pid')->end()
                ->scalarNode('timezone')->cannotBeEmpty()->defaultValue('UTC')->end()
                ->arrayNode('logging')
                    ->children()
                        ->scalarNode('directory')->defaultValue('./logs/')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('processes')
                    ->prototype('array')
                        ->children()
                            //description & metadata
                            ->scalarNode('name')->cannotBeEmpty()->end()
                            ->arrayNode('roles')->prototype('scalar')->end()->end()
                            //process information
                            ->scalarNode('up')->isRequired()->cannotBeEmpty()->end()
                            //failure management
                            ->scalarNode('resurrect_after')->defaultValue(5)->end()
                            //log files
                            ->booleanNode('log_forwarding')->defaultValue(true)->end()
                            ->scalarNode('stdout_file')->defaultValue('%name%.log')->cannotBeEmpty()->end()
                            ->scalarNode('stderr_file')->defaultValue('%name%.err.log')->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}