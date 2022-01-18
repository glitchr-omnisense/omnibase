<?php

namespace Base\Twig\Extension;

use Base\Service\LocaleProviderInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LocaleTwigExtension extends AbstractExtension
{
    public function __construct(Environment $twig, LocaleProviderInterface $localeProvider)
    {
        $this->twig = $twig;
        $this->localeProvider = $localeProvider;
    }

    public function getFunctions() : array
    {
        return [
            new TwigFunction('render_locale', [$this, 'renderLocale'], ['is_safe' => ['all']]),
        ];
    }

    public function renderLocale(string $switchRoute, string $template = "@Base/locale/dropdown.html.twig"): ?string
    {
        return $this->twig->render($template, [
                    "switch_route" => $switchRoute,
                    "available_locales" => $this->localeProvider->getAvailableLocales(), 
                    "current_locale"    => $this->localeProvider->getLocale(), 
        ]);
    }

    public function getName()
    {
        return 'locale_extension';
    }
}
