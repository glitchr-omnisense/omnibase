<?php

namespace Base\DependencyInjection\Compiler;

use Base\Service\Sharer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SharerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(Sharer::class))
            return;

        $definition = $container->findDefinition(Sharer::class);
        $taggedServices = $container->findTaggedServiceIds('base.service.sharer');
        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addAdapter', [new Reference($id)]);
    }
}