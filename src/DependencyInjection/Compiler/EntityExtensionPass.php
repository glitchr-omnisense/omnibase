<?php

namespace Base\DependencyInjection\Compiler;

use Base\Database\Factory\EntityExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class EntityExtensionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(EntityExtension::class))
            return;

        $definition     = $container->findDefinition(EntityExtension::class);
        $taggedServices = $container->findTaggedServiceIds('base.entity_extension');
        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addExtension', [new Reference($id)]);
    }
}
