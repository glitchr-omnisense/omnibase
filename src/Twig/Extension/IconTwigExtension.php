<?php

namespace Base\Twig\Extension;

use Base\Service\Icons;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * `|iconify` in a template: omnibase's Icons (an icon's name, a list, an
 * enumeration's or a route's icons, as HTML).
 *
 * Framework-free (audit "lesringostars face au master plan", R24): Symfony
 * registers it with IconProvider's Icons, Laravel (omnibridge/laravel) with
 * the same adapters and the Routes contract. It was MediaTwigExtension's,
 * under the same name.
 */
final class IconTwigExtension extends AbstractExtension
{
    public function __construct(private readonly Icons $icons)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('iconify', [$this->icons, 'iconify'], ['is_safe' => ['all']]),
        ];
    }
}
