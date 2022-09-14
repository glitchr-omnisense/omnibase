<?php

namespace Base\Twig\Extension;

use Base\Service\LocaleProvider;
use Base\Service\LocaleProviderInterface;
use Base\Service\Translator;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class LangTwigExtension extends AbstractExtension
{
    public function __construct(LocaleProviderInterface $localeProvider) { $this->localeProvider = $localeProvider; }

    public function getName() { return 'lang_extension'; }

    public function getFilters() : array
    {
        return [
            new TwigFilter('trans',        [Translator::class, 'trans']),
            new TwigFilter('trans_quiet',  [Translator::class, 'transQuiet']),
            new TwigFilter('trans_exists', [Translator::class, 'transExists']),

            new TwigFilter('trans_time',         [Translator::class, 'transTime']),
            new TwigFilter('trans_enum',         [Translator::class, 'transEnum']),
            new TwigFilter('trans_enumExists',         [Translator::class, 'transEnumExists']),
            new TwigFilter('trans_entity',       [Translator::class, 'transEntity']),
            new TwigFilter('trans_entityExists',       [Translator::class, 'transEntityExists']),

            new TwigFilter('lang',         [LocaleProvider::class, 'getLang']),
            new TwigFilter('lang_name',    [LocaleProvider::class, 'getLangName']),
            new TwigFilter('country',      [LocaleProvider::class, 'getCountry']),
            new TwigFilter('country_name', [LocaleProvider::class, 'getCountryName']),

        ];
    }

    public function getFunctions() : array
    {
        return [
            new TwigFunction('render_locale', [$this, 'renderLocale'], ["needs_environment" => true, 'is_safe' => ['all']]),
            new TwigFunction('compatible_locale', [$this, 'compatibleLocale'], ['is_safe' => ['all']]),
        ];
    }

    public function compatibleLocale(string $locale, string $preferredLocale, ?array $availableLocales = null): ?string
    {
        return $this->localeProvider->compatibleLocale($locale, $preferredLocale, $availableLocales);
    }

    public function renderLocale(Environment $twig, string $switchRoute, array $options = [], string $template = "@Base/locale/dropdown.html.twig"): ?string
    {
        return $twig->render($template, array_merge($options, [
            "switch_route" => $switchRoute,
            "available_locales" => $this->localeProvider->getAvailableLocales(),
            "current_locale"    => $this->localeProvider->getLocale()
        ]));
    }
}
