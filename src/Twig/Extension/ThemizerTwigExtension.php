<?php

namespace Base\Twig\Extension;

use Base\Service\Themizer;
use Base\Service\ThemizerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * omnibase's theme filters on its Themizer. The theme menus,
 * render_theme_modes(), _filters() and _widths(), are MenuTwigExtension's
 * (framework-free, the same under Laravel).
 */
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
        ];
    }
}
