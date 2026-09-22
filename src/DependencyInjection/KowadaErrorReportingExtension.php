<?php

namespace Kowada\ErrorReportingBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Loads the bundle's service definitions and exposes its configuration as container parameters.
 */
class KowadaErrorReportingExtension extends Extension {

    /**
     * @param array<int, array<string, mixed>> $configs Raw configuration arrays from all `kowada_error_reporting` config trees.
     */
    public function load(array $configs, ContainerBuilder $container): void {
        $configuration = new Configuration();

        /**
         * @var array{receiver: string|null, sender_address: string|null, sender_name: string|null, app_name: string|null} $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('kowada_error_reporting.receiver', $config['receiver']);
        $container->setParameter('kowada_error_reporting.sender_address', $config['sender_address']);
        $container->setParameter('kowada_error_reporting.sender_name', $config['sender_name']);
        $container->setParameter('kowada_error_reporting.app_name', $config['app_name']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.yaml');
    }

}
