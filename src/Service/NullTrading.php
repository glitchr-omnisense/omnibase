<?php

namespace Base\Service;

use DateTime;
use Exchanger\Contract\ExchangeRate;

/**
 * The currency rates where florianv/swap is not installed: Base\Service\Trading is declared only with it (and
 * symfony/http-client's PSR-18 adapter, which it hands Swap), and `trading_market` / TradingInterface are then this
 * one (Base\Discovery's `fallbacks`), so the Twig filters, the money field and the facade still have their service.
 *
 * It knows no rate: an amount is formatted in its own currency (|format_currency, Base\View\Money) and converted only
 * into itself. A rate needs florianv/exchanger's ExchangeRate, which florianv/swap brings: addFallback() refuses one
 * aloud rather than drop it, so an application that converts between currencies is told to require florianv/swap
 * instead of showing amounts unconverted.
 */
final class NullTrading implements TradingInterface
{
    private ?string $renderedCurrency = null;

    public function normalize(string $source, string $target, mixed $value, null|string|int|DateTime $datetime): ?ExchangeRate
    {
        return null;
    }

    public function get(string $from, string $to, array $options = []): ?ExchangeRate
    {
        return null;
    }

    public function getFallback(string $source, ?string $target = null): ExchangeRate|array|null
    {
        return null === $target ? [] : null;
    }

    public function getLatest(string $source, string $target, array $options = []): ?ExchangeRate
    {
        return null;
    }

    public function isLive(): bool
    {
        return false;
    }

    public function setLive(bool $live): static
    {
        return $this;
    }

    /** The amount itself in its own currency; into another, the exception Trading throws without a rate. */
    public function convert(string|float $cash, string $source, string $target, array $options = [], string $timeAgo = 'now'): ?float
    {
        if ($source === $target) {
            return (float) $cash;
        }

        throw new \Exception(sprintf('No source available for currency exchange (%s/%s): florianv/swap is not installed.', $source, $target));
    }

    public function convertLatest(string|float $cash, string $source, string $target, array $options = []): ?float
    {
        return $source === $target ? (float) $cash : null;
    }

    public function convertFallback(string|float $cash, string $source, string $target): ?float
    {
        return $source === $target ? (float) $cash : null;
    }

    public function getRenderedCurrency(): ?string
    {
        return $this->renderedCurrency;
    }

    public function setRenderedCurrency(?string $renderedCurrency)
    {
        $this->renderedCurrency = $renderedCurrency;

        return $this;
    }

    public function addFallback(string $source, string $target, mixed $value, null|string|int|DateTime $date = 'now'): self
    {
        throw new \LogicException(sprintf('The rate %s/%s cannot be kept: florianv/swap is not installed (Base\Service\Trading is declared with it, NullTrading knows no rate). Require florianv/swap to convert between currencies.', $source, $target));
    }

    public function removeFallback(string $source, string $target): self
    {
        return $this;
    }
}
