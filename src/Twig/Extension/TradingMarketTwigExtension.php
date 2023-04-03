<?php

namespace Base\Twig\Extension;

use Base\Database\Type\EnumType;
use Base\Service\Model\Color\Intl\Colors;
use Base\Service\IconProvider;
use Base\Service\MediaService;
use Base\Service\Model\ColorizeInterface;
use Base\Service\TradingMarketInterface;
use Base\Service\TranslatorInterface;
use DateInterval;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Type;
use ReflectionFunction;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Twig\Extra\Intl\IntlExtension;
use Twig\TwigFunction;

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
        $this->intlExtension  = new IntlExtension();
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

    public function formatCurrency($amount, string $currency, array $attrs = [], string $locale = null): string
    {
        $rate = 1.00;
        $applyRate     = array_pop_key("use_rate", $attrs) ?? true;
        $scalingFactor = array_pop_key("scale", $attrs) ?? 100;

        if ($applyRate) {
            $targetCurrency = $this->tradingMarket->getRenderedCurrency() ?? $currency;
            $rate = $this->tradingMarket->getFallback($currency, $targetCurrency)?->getValue();

            if ($rate !== null) {
                $currency = $targetCurrency;
            } else {
                $rate = 1.0;
            }
        }

        return $this->intlExtension->formatCurrency($amount*$rate / $scalingFactor, $currency, $attrs, $locale);
    }

    public function applyCurrencyRate($amount, string $currency, array $attrs = [], string $locale = null): ?float
    {
        $targetCurrency = $this->tradingMarket->getRenderedCurrency() ?? $currency;
        $rate = $this->tradingMarket->getFallback($currency, $targetCurrency)?->getValue();
        if ($rate === null) {
            return null;
        }

        return $amount*$rate;
    }
}
