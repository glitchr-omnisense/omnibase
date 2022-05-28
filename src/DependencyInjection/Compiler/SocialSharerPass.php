<?php

namespace Base\DependencyInjection\Compiler;

use Base\Service\SocialSharer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SocialSharerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(SocialSharer::class))
            return;

        $definition = $container->findDefinition(SocialSharer::class);

        $taggedServices = $container->findTaggedServiceIds('base.social_sharer');
        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addAdapter', [new Reference($id)]);
    }
}