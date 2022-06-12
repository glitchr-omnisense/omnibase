<?php

namespace Base\DependencyInjection\Compiler;

use Base\Service\IconProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class IconProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(IconProvider::class))
            return;

        $definition = $container->findDefinition(IconProvider::class);

        $taggedServices = $container->findTaggedServiceIds('base.service.icon');
        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addAdapter', [new Reference($id)]);
    }
}