<?php

namespace Base\DependencyInjection\Compiler;

use Base\Service\Obfuscator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ObfuscatorCompressionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(Obfuscator::class))
            return;

        $definition = $container->findDefinition(Obfuscator::class);

        $taggedServices = $container->findTaggedServiceIds('obfuscator.compression');
        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addCompression', [new Reference($id)]);
    }
}