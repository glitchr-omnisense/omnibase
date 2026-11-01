<?php

namespace Base\View;

use Base\Service\TradingInterface;

/**
 * An amount in the currency the visitor reads (plan "omnibase agnostic", step F4): the logic of
 * the |format_currency and |apply_currency_rate filters, on the Trading service's rates, formatted
 * by PHP's intl as Twig's format_currency() formats it (twig/intl-extra), without Twig -
 * TradingTwigExtension calls it, Blade and a React page's props the same.
 */
final class Money
{
    private const ATTRIBUTES = [
        'grouping_used' => \NumberFormatter::GROUPING_USED,
        'decimal_always_shown' => \NumberFormatter::DECIMAL_ALWAYS_SHOWN,
        'max_integer_digit' => \NumberFormatter::MAX_INTEGER_DIGITS,
        'min_integer_digit' => \NumberFormatter::MIN_INTEGER_DIGITS,
        'integer_digit' => \NumberFormatter::INTEGER_DIGITS,
        'max_fraction_digit' => \NumberFormatter::MAX_FRACTION_DIGITS,
        'min_fraction_digit' => \NumberFormatter::MIN_FRACTION_DIGITS,
        'fraction_digit' => \NumberFormatter::FRACTION_DIGITS,
        'multiplier' => \NumberFormatter::MULTIPLIER,
        'grouping_size' => \NumberFormatter::GROUPING_SIZE,
        'rounding_mode' => \NumberFormatter::ROUNDING_MODE,
        'rounding_increment' => \NumberFormatter::ROUNDING_INCREMENT,
        'format_width' => \NumberFormatter::FORMAT_WIDTH,
        'padding_position' => \NumberFormatter::PADDING_POSITION,
        'secondary_grouping_size' => \NumberFormatter::SECONDARY_GROUPING_SIZE,
        'significant_digits_used' => \NumberFormatter::SIGNIFICANT_DIGITS_USED,
        'min_significant_digits_used' => \NumberFormatter::MIN_SIGNIFICANT_DIGITS,
        'max_significant_digits_used' => \NumberFormatter::MAX_SIGNIFICANT_DIGITS,
        'lenient_parse' => \NumberFormatter::LENIENT_PARSE,
    ];
    private const ROUNDING = [
        'ceiling' => \NumberFormatter::ROUND_CEILING,
        'floor' => \NumberFormatter::ROUND_FLOOR,
        'down' => \NumberFormatter::ROUND_DOWN,
        'up' => \NumberFormatter::ROUND_UP,
        'halfeven' => \NumberFormatter::ROUND_HALFEVEN,
        'halfdown' => \NumberFormatter::ROUND_HALFDOWN,
        'halfup' => \NumberFormatter::ROUND_HALFUP,
    ];
    private const PADDING = [
        'before_prefix' => \NumberFormatter::PAD_BEFORE_PREFIX,
        'after_prefix' => \NumberFormatter::PAD_AFTER_PREFIX,
        'before_suffix' => \NumberFormatter::PAD_BEFORE_SUFFIX,
        'after_suffix' => \NumberFormatter::PAD_AFTER_SUFFIX,
    ];

    public function __construct(private readonly TradingInterface $trading)
    {
    }

    /**
     * An amount in minor units (cents: attribute `scale`, 100 by default) formatted in a currency;
     * converted first to the currency the visitor reads when a rate is known (attribute `use_rate`,
     * true by default). The other attributes are the number formatter's (fraction_digit...).
     *
     * @param array<string, mixed> $attrs
     */
    public function formatCurrency(mixed $amount, string $currency, array $attrs = [], ?string $locale = null): string
    {
        $rate = 1.00;
        $applyRate = array_pop_key('use_rate', $attrs) ?? true;
        $scalingFactor = array_pop_key('scale', $attrs) ?? 100;
        if ($applyRate) {
            $targetCurrency = $this->trading->getRenderedCurrency() ?? $currency;
            $rate = $this->trading->getFallback($currency, $targetCurrency)?->getValue();
            if (null !== $rate) {
                $currency = $targetCurrency;
            } else {
                $rate = 1.0;
            }
        }

        return self::currency($amount * $rate / $scalingFactor, $currency, $attrs, $locale);
    }

    /** An amount converted to the currency the visitor reads; null when no rate is known. */
    public function applyCurrencyRate(mixed $amount, string $currency): ?float
    {
        $targetCurrency = $this->trading->getRenderedCurrency() ?? $currency;
        $rate = $this->trading->getFallback($currency, $targetCurrency)?->getValue();
        if (null === $rate) {
            return null;
        }

        return $amount * $rate;
    }

    /**
     * An amount formatted in a currency, as twig/intl-extra's format_currency() formats it.
     *
     * @param array<string, mixed> $attrs the number formatter's attributes, by their names in Twig
     */
    public static function currency(int|float $amount, string $currency, array $attrs = [], ?string $locale = null): string
    {
        static $formatters = [];

        $locale ??= \Locale::getDefault();
        ksort($attrs);
        $hash = $locale.'|'.json_encode($attrs);
        if (!isset($formatters[$hash])) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            foreach ($attrs as $name => $value) {
                if (!isset(self::ATTRIBUTES[$name])) {
                    throw new \InvalidArgumentException(sprintf('The number formatter attribute "%s" does not exist, known attributes are: "%s".', $name, implode('", "', array_keys(self::ATTRIBUTES))));
                }
                if ('rounding_mode' === $name) {
                    $value = self::ROUNDING[$value] ?? throw new \InvalidArgumentException(sprintf('The number formatter rounding mode "%s" does not exist, known modes are: "%s".', $value, implode('", "', array_keys(self::ROUNDING))));
                } elseif ('padding_position' === $name) {
                    $value = self::PADDING[$value] ?? throw new \InvalidArgumentException(sprintf('The number formatter padding position "%s" does not exist, known positions are: "%s".', $value, implode('", "', array_keys(self::PADDING))));
                }
                $formatter->setAttribute(self::ATTRIBUTES[$name], $value);
            }
            $formatters[$hash] = $formatter;
        }

        if (false === $ret = $formatters[$hash]->formatCurrency($amount, $currency)) {
            throw new \RuntimeException('Unable to format the given number as a currency.');
        }

        return $ret;
    }
}
