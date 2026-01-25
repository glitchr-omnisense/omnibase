<?php

namespace Base\Twig\Extension;

use Base\Service\Themizer;
use Base\Service\ThemizerInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ThemizerTwigExtension extends AbstractExtension
{
    protected ThemizerInterface $themizer;

    public function __construct(ThemizerInterface $themizer)
    {
        $this->themizer = $themizer;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'theme_extension';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('mode', [Themizer::class, 'getMode']),
            new TwigFilter('filters', [Themizer::class, 'getFilters']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('compatible_theme', [Themizer::class, 'compatibleLocale'], ['is_safe' => ['all']]),
            new TwigFunction('render_theme_modes', [$this, 'renderModes'], ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('render_theme_filters', [$this, 'renderFilters'], ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('render_theme_widths', [$this, 'renderWidths'], ['needs_environment' => true, 'is_safe' => ['all']]),
        ];
    }

    public function renderModes(Environment $twig, array $options = [], string $template = '@Base/themizer/dropdown.html.twig'): ?string
    {
        return $twig->render($template, array_merge($options, [
            'available_theme_modes' => $this->themizer->getAvailableModes(),
            'current_theme_mode' => $this->themizer->getMode(),
        ]));
    }

    public function renderFilters(Environment $twig, array $options = [], string $template = '@Base/themizer/filters/dropdown.html.twig'): ?string
    {
        return $twig->render($template, array_merge($options, [
            'available_theme_filters' => $this->themizer->getAvailableFilters(),
            'current_theme_filter' => $this->themizer->getFilter(),
        ]));
    }

    public function renderWidths(Environment $twig, array $options = [], string $template = '@Base/themizer/widths/dropdown.html.twig'): ?string
    {
        return $twig->render($template, array_merge($options, [
            'available_theme_widths' => $this->themizer->getAvailableWidths(),
            'current_theme_width' => $this->themizer->getWidth(),
        ]));
    }
}
