<?php

namespace Base\DependencyInjection;

use Base\Annotations\AnnotationInterface;
use Base\Database\Factory\EntityExtensionInterface;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class BaseExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        //
        // Load service declaration (includes services, controllers,..)

        // Format XML
        $loader = new XmlFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.xml');
        $loader->load('services-public.xml');
        $loader->load('services-fix.xml');
        $loader->load('services-decoration.xml');

        // Configuration file: ./config/package/base.yaml
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);
        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->getRootNode()->getNode()->getName());

        // Override and merge form_themes.. to add some features..
        $container->setParameter('twig.form.resources', array_merge(
            $config["twig"]["form_themes"],
            $container->getParameter('twig.form.resources')
        ));

        $container->registerForAutoconfiguration(EntityExtensionInterface::class)->addTag('base.entity_extension');
        $container->registerForAutoconfiguration(AnnotationInterface::class)->addTag('base.annotation');
        $container->registerForAutoconfiguration(IconAdapterInterface::class)->addTag('base.icon_provider');
        $container->registerForAutoconfiguration(SharerAdapterInterface::class)->addTag('base.service.sharer');
        $container->registerForAutoconfiguration(CurrencyApiInterface::class)->addTag('base.currency_api');

        $this->createMetadataCache("entity_manager", $container);
    }

    public function setConfiguration(ContainerBuilder $container, array $config, $globalKey = "")
    {
        foreach ($config as $key => $value) {

            if (!empty($globalKey)) $key = $globalKey . "." . $key;

            if (is_array($value)) $this->setConfiguration($container, $value, $key);
            else $container->setParameter($key, $value);
        }
    }

    protected function getObjectManagerElementName($name): string
    {
        return 'doctrine.orm.' . $name;
    }

    private function createMetadataCache(string $objectManagerName, ContainerBuilder $container): void
    {
        $aliasId = $this->getObjectManagerElementName(sprintf('%s_%s', $objectManagerName, 'metadata_completor_cache'));
        $cacheId = sprintf('cache.doctrine.orm.%s.%s', $objectManagerName, 'metadata');

        $cache = new Definition(ArrayAdapter::class);

        if (! $container->getParameter('kernel.debug')) {
            $phpArrayFile         = '%kernel.cache_dir%' . sprintf('/doctrine/orm/%s_metadata_completor.php', $objectManagerName);
            $cacheWarmerServiceId = $this->getObjectManagerElementName(sprintf('%s_%s', $objectManagerName, 'metadata_completor_cache_warmer'));

            $container->register($cacheWarmerServiceId, MetadataCompletorWarmer::class)
                ->setArguments([
                    new Reference(sprintf('doctrine.orm.%s_entity_manager', $objectManagerName)), $phpArrayFile])
                ->addTag('kernel.cache_warmer', ['priority' => 1000]); // priority should be higher than ProxyCacheWarmer

            $cache = new Definition(PhpArrayAdapter::class, [$phpArrayFile, $cache]);
        }

        $container->setDefinition($cacheId, $cache);
        $container->setAlias($aliasId, $cacheId);
    }
}
