<?php

namespace Base\DependencyInjection\Compiler;

use Base\Twig\Environment;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TagRendererPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(Environment::class))
            return;

        $definition = $container->findDefinition(Environment::class);

        $taggedServices = $container->findTaggedServiceIds('twig.tag_renderer');
        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addRenderer', [new Reference($id)]);
    }
}
