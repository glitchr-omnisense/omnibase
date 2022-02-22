<?php

namespace Base\DependencyInjection\Compiler;

use Base\Service\CurrencyApi;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CurrencyApiPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(CurrencyApi::class))
            return;

        $definition = $container->findDefinition(CurrencyApi::class);

        $taggedServices = $container->findTaggedServiceIds('base.currency_api');
        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addProvider', [new Reference($id)]);
    }
}