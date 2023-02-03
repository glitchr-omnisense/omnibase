<?php

namespace Base\Twig\Extension;

use Base\Entity\User;
use Base\Service\Localizer;
use Base\Service\LocalizerInterface;
use Base\Service\Translator;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class LocalizerTwigExtension extends AbstractExtension
{
    /**
     * @var Localizer
     */
    protected $localizer;

    public function __construct(LocalizerInterface $localizer) { $this->localizer = $localizer; }

    public function getName() { return 'locale_extension'; }

    public function getFilters() : array
    {
        return [
            new TwigFilter('lang',         [Localizer::class, 'getLocaleLang']),
            new TwigFilter('lang_name',    [Localizer::class, 'getLocaleLangName']),
            new TwigFilter('country',      [Localizer::class, 'getLocaleCountry']),
            new TwigFilter('country_name', [Localizer::class, 'getLocaleCountryName']),

        ];
    }

    public function getFunctions() : array
    {
        return [
            new TwigFunction('compatible_locale', [Localizer::class, 'compatibleLocale'], ['is_safe' => ['all']]),
            new TwigFunction('render_locale',   [$this, 'renderLocale'], ["needs_environment" => true, 'is_safe' => ['all']]),
            new TwigFunction('render_timezone', [$this, 'renderTimezone'], ["needs_environment" => true, 'is_safe' => ['all']]),
        ];
    }

    public function renderLocale(Environment $twig, array $options = [], string $template = "@Base/localizer/locale_dropdown.html.twig"): ?string
    {
        return $twig->render($template, array_merge($options, [
            "available_locales" => $this->localizer->getAvailableLocales(),
            "current_locale"    => $this->localizer->getLocale()
        ]));
    }

    public function renderCountry(Environment $twig, array $options = [], string $template = "@Base/localizer/country_dropdown.html.twig"): ?string
    {
        return $twig->render($template, array_merge($options, [
            "available_countries" => $this->localizer->getAvailableLocaleCountries(),
            "country"    => $this->localizer->getLocaleCountry()
        ]));
    }

    public function renderTimezone(Environment $twig, array $options = [], string $template = "@Base/localizer/timezone_dropdown.html.twig"): ?string
    {
        return $twig->render($template, array_merge($options, [
            "available_timezones" => $this->localizer->getAvailableTimezones(),
            "current_timezone"    => $this->localizer->getTimezone()
        ]));
    }


    public function renderCurrency(Environment $twig, array $options = [], string $template = "@Base/localizer/currency_dropdown.html.twig"): ?string
    {
        return $twig->render($template, array_merge($options, [
            "available_currencies" => $this->localizer->getAvailableCurrencies(),
            "current_currency"    => $this->localizer->getCurrency()
        ]));
    }
}
