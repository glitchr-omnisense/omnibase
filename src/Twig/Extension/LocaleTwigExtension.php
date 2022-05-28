<?php

namespace Base\Twig\Extension;

use Base\Service\LocaleProviderInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LocaleTwigExtension extends AbstractExtension
{
    public function __construct(LocaleProviderInterface $localeProvider) { $this->localeProvider = $localeProvider; }
    
    public function getName() { return 'locale_extension'; }
    public function getFunctions() : array
    {
        return [
            new TwigFunction('render_locale', [$this, 'renderLocale'], ["needs_environment" => true, 'is_safe' => ['all']]),
        ];
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
