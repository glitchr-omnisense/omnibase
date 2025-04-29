<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\DatabaseSubscriber\EnumSubscriber;
use Composer\InstalledVersions;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DoctrineEnumPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!InstalledVersions::isInstalled('doctrine/dbal')) {
            return;
        }

        $dbalVersion = InstalledVersions::getVersion('doctrine/dbal');
        if (version_compare($dbalVersion, '4.0.0', '<')) return;

        $definition = $container->getDefinition('doctrine.dbal.default_connection.event_manager');
        $definition->addMethodCall('addEventSubscriber', [new Reference(EnumSubscriber::class)]);

        if (!$container->has('doctrine.orm.default_entity_manager')) {
            return;
        }
    }
}