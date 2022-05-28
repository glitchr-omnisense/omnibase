<?php

namespace Base\DependencyInjection\Compiler;

use Base\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AnnotationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(AnnotationReader::class)) {
            return;
        }

        $definition     = $container->findDefinition(AnnotationReader::class);
        $taggedServices = $container->findTaggedServiceIds('base.annotation');

        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addAnnotation', [new Reference($id)]);
    }
}
