<?php

namespace Base\DependencyInjection\Compiler;

use Base\Service\CurrencyApi;
use Base\Service\TradingMarket;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TradingMarketPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(TradingMarket::class))
            return;

        $definition = $container->findDefinition(TradingMarket::class);

        $taggedServices = $container->findTaggedServiceIds('currency.api');
        foreach ($taggedServices as $id => $tags)
            $definition->addMethodCall('addProvider', [new Reference($id)]);
    }
}