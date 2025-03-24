<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_blob_connector_filesystem');

        $treeBuilder
            ->getRootNode()
                ->children()
                    ->scalarNode('path')
                        ->defaultValue('blobFiles')
                    ->end()
                    ->booleanNode('create_path')
                        ->info('If path should be created if it doesn\'t exist')
                        ->defaultFalse()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
