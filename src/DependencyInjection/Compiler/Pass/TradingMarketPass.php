<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Annotations\AnnotationReader;
use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Service\CurrencyApi;
use Base\Service\TradingMarket;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TradingMarketPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return "currency.api";
    }

    public function classFqcn(): string
    {
        return TradingMarket::class;
    }
    public function addMethod(): string
    {
        return "addProvider";
    }
}
