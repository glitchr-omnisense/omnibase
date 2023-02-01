<?php

namespace Base\Service;

use Exchanger\Contract\ExchangeRate;

interface TradingMarketInterface
{
    public function normalize(string $source, string $target, mixed $value, null|string|int|\DateTime $datetime): ?ExchangeRate;

    public function get(string $from, string $to, array $options, string $timeAgo): ?ExchangeRate;
    public function getFallback(string $source, ?string $target = null): ExchangeRate|array|null;
    public function getLatest(string $source, string $target, array $options): ?ExchangeRate;
    //public function refresh(): bool; // not satisfying way to refresh cache from swap found

    public function convert(string|float $cash, string $source, string $target, array $options, string $timeAgo): ?float;
    public function convertLatest(string|float $cash, string $source, string $target, array $options): ?float;
    public function convertFallback(string|float $cash, string $source, string $target): ?float;

    public function getRenderedCurrency(): ?string;
    public function setRenderedCurrency(?string $renderedCurrency);

    public function addFallback(string $source, string $target, mixed $value, null|string|int|\DateTime $timestamp): self;
    public function removeFallback(string $source, string $target): self;
}