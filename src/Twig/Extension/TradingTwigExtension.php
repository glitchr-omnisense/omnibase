<?php

namespace Base\Twig\Extension;

use Base\Service\TradingInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Base\View\Money;

final class TradingTwigExtension extends AbstractExtension
{
    /**
     * @var TradingInterface
     */
    protected $tradingMarket;

    private readonly Money $money;

    public function __construct(TradingInterface $tradingMarket)
    {
        $this->tradingMarket = $tradingMarket;
        $this->money = new Money($tradingMarket);
    }

    public function getFilters(): array
    {
        return
            [
                new TwigFilter('format_currency', [$this, 'formatCurrency']),
                new TwigFilter('apply_currency_rate', [$this, 'applyCurrencyRate']),
            ];
    }

    public function getFunctions(): array
    {
        return
            [
                new TwigFunction('apply_currency_rate', [$this, 'applyCurrencyRate']),
            ];
    }

    /** An amount in the currency the visitor reads (Base\View\Money). */
    public function formatCurrency($amount, string $currency, array $attrs = [], ?string $locale = null): string
    {
        return $this->money->formatCurrency($amount, $currency, $attrs, $locale);
    }

    /** An amount converted to the currency the visitor reads, null without a rate (Base\View\Money). */
    public function applyCurrencyRate($amount, string $currency, array $attrs = [], ?string $locale = null): ?float
    {
        return $this->money->applyCurrencyRate($amount, $currency);
    }
}
