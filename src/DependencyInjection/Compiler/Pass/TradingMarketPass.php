<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Service\TradingMarket;

class TradingMarketPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return 'currency.api';
    }

    public function classFqcn(): string
    {
        return TradingMarket::class;
    }

    public function addMethod(): string
    {
        return 'addProvider';
    }
}
