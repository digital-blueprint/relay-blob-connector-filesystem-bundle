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
                    ->scalarNode('database_url')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->defaultValue('%env(resolve:DATABASE_URL)%')
                    ->end()
                    ->scalarNode('path')
                        ->defaultValue('blobFiles')
                    ->end()
                    ->scalarNode('linkUrl')
                    ->end()
                    ->scalarNode('linkExpireTime')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
