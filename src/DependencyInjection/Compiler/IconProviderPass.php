<?php

namespace Base\DependencyInjection\Compiler;

use Base\Service\IconService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class IconProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(IconService::class)) {
            return;
        }

        $definition = $container->findDefinition(IconService::class);

        $taggedServices = $container->findTaggedServiceIds('base.icon_provider');
        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addProvider', [new Reference($id)]);
    }
}