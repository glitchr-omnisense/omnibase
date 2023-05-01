<?php

namespace Base\Twig\Extension;

use Base\Service\TradingMarketInterface;
use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 *
 */
final class TradingMarketTwigExtension extends AbstractExtension
{
    /**
     * @var IntlExtension
     */
    protected $intlExtension;

    /**
     * @var TradingMarketInterface
     */
    protected $tradingMarket;

    public function __construct(TradingMarketInterface $tradingMarket)
    {
        $this->tradingMarket = $tradingMarket;
        $this->intlExtension = new IntlExtension();
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

    /**
     * @param $amount
     * @param string $currency
     * @param array $attrs
     * @param string|null $locale
     * @return string
     * @throws RuntimeError
     */
    public function formatCurrency($amount, string $currency, array $attrs = [], string $locale = null): string
    {
        $rate = 1.00;
        $applyRate = array_pop_key('use_rate', $attrs) ?? true;
        $scalingFactor = array_pop_key('scale', $attrs) ?? 100;

        if ($applyRate) {
            $targetCurrency = $this->tradingMarket->getRenderedCurrency() ?? $currency;
            $rate = $this->tradingMarket->getFallback($currency, $targetCurrency)?->getValue();

            if (null !== $rate) {
                $currency = $targetCurrency;
            } else {
                $rate = 1.0;
            }
        }

        return $this->intlExtension->formatCurrency($amount * $rate / $scalingFactor, $currency, $attrs, $locale);
    }

    /**
     * @param $amount
     * @param string $currency
     * @param array $attrs
     * @param string|null $locale
     * @return float|null
     */
    public function applyCurrencyRate($amount, string $currency, array $attrs = [], string $locale = null): ?float
    {
        $targetCurrency = $this->tradingMarket->getRenderedCurrency() ?? $currency;
        $rate = $this->tradingMarket->getFallback($currency, $targetCurrency)?->getValue();
        if (null === $rate) {
            return null;
        }

        return $amount * $rate;
    }
}
