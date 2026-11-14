<?php

namespace Base\Twig\Extension;

use Base\Service\ThemizerInterface;
use Omnibridge\Contract\Locales;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Base\View\Menus;

/**
 * The visitor's menus of a page: its language, `render_locale()` (the host's
 * Locales contract: the site's languages and the request's), and its theme,
 * `render_theme_modes()`, `render_theme_filters()`, `render_theme_widths()`
 * (omnibase's ThemizerInterface: the modes, filters and widths the site
 * offers and the visitor chose) - each a template rendered with the
 * choices, the visitor's marked.
 *
 * Framework-free (plan "omnibase agnostique", step R9 of the audit
 * "lesringostars face au master plan"): Symfony registers it with omnibase's
 * Localizer (Omnibridge\Symfony\Omnibase\LocalizerLocales) and Themizer, Laravel
 * with omnibridge/laravel's AppLocales and Theme - the same menus from the
 * same code. Formerly in LocalizerTwigExtension and ThemizerTwigExtension,
 * under the same names and defaults.
 */
final class MenuTwigExtension extends AbstractExtension
{
    private readonly Menus $menus;

    public function __construct(
        private readonly Locales $locales,
        private readonly ThemizerInterface $themizer,
    ) {
        $this->menus = new Menus($locales, $themizer);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_locale', $this->renderLocale(...), ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('render_theme_modes', $this->renderModes(...), ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('render_theme_filters', $this->renderFilters(...), ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('render_theme_widths', $this->renderWidths(...), ['needs_environment' => true, 'is_safe' => ['all']]),
        ];
    }

    public function renderLocale(Environment $twig, array $options = [], string $template = '@Base/localizer/locale_dropdown.html.twig'): ?string
    {
        return $twig->render($template, array_merge($options, $this->menus->locale()));
    }

    public function renderModes(Environment $twig, array $options = [], string $template = '@Base/themizer/dropdown.html.twig'): ?string
    {
        return $twig->render($template, array_merge($options, $this->menus->modes()));
    }

    public function renderFilters(Environment $twig, array $options = [], string $template = '@Base/themizer/filters/dropdown.html.twig'): ?string
    {
        return $twig->render($template, array_merge($options, $this->menus->filters()));
    }

    public function renderWidths(Environment $twig, array $options = [], string $template = '@Base/themizer/widths/dropdown.html.twig'): ?string
    {
        return $twig->render($template, array_merge($options, $this->menus->widths()));
    }
}
