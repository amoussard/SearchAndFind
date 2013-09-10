<?php

namespace Drassuom\ImportBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class DrassuomImportExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        var_dump($config);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('converter.yml');

        $container->setParameter("drassuom_import.config", $config);
        $container->setParameter("drassuom_import.config.reencoding_path", $config['reencoding_path']);
        $container->setAlias('entity_finder', 'drassuom_import.converter.orm.entity_finder');
        $container->setAlias('mapping_finder', 'drassuom_import.converter.orm.mapping_finder');
    }
}
