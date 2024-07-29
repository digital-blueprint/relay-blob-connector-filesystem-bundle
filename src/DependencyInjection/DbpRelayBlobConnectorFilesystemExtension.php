<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\DependencyInjection;

use Dbp\Relay\BlobConnectorFilesystemBundle\Service\ConfigurationService;
use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayBlobConnectorFilesystemExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $this->addRouteResource($container, __DIR__.'/../Resources/config/routing.yaml', 'yaml');

        $definition = $container->getDefinition(ConfigurationService::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);
    }

    public function prepend(ContainerBuilder $container): void
    {
    }
}
