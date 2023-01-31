<?php

namespace Base\Service;

use Exchanger\Contract\ExchangeRate;

interface TradingMarketInterface
{
    public function getLatest(string $source, string $target): ?ExchangeRate;
    public function get(string $source, string $target, string $timeAgo): ?ExchangeRate;
    //public function refresh(): bool; // not satisfying way to refresh cache from swap found

    public function convert(string|float $cash, string $source, string $target): float;
}