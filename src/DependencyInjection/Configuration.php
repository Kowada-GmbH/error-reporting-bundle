<?php

namespace Kowada\ErrorReportingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the `kowada_error_reporting` configuration tree exposed to consuming applications.
 */
class Configuration implements ConfigurationInterface {

    /**
     * @return TreeBuilder The tree defining the `receiver`, `sender_address`, `sender_name` and `app_name` options.
     */
    public function getConfigTreeBuilder(): TreeBuilder {
        $treeBuilder = new TreeBuilder('kowada_error_reporting');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('receiver')->defaultNull()->end()
                ->scalarNode('sender_address')->defaultNull()->end()
                ->scalarNode('sender_name')->defaultNull()->end()
                ->scalarNode('app_name')->defaultNull()->end()
            ->end();

        return $treeBuilder;
    }

}
