<?php

namespace Base\Service;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\IconProvider\IconAdapterInterface;

/**
 * omnibase's `|iconify`: an icon's name, a list of them, an enumeration's or
 * an IconizeInterface's icons, or a route's (the icons its controller
 * declares, #[Iconize]) as HTML, by the first adapter that knows the name
 * (Font Awesome, Bootstrap's icons...), a question mark for none.
 *
 * Framework-free (audit "lesringostars face au master plan", R24): the
 * adapters and a route's icons are given - under Symfony by IconProvider
 * (its adapters, the route icons it reads from the router: icons()), under
 * Laravel by omnibridge/laravel (the same adapters, the Routes contract's
 * icons()). The rule was IconProvider::iconify(), which now answers by it.
 */
final class Icons
{
    /**
     * @param iterable<IconAdapterInterface>|\Closure(): iterable<IconAdapterInterface> $adapters
     * @param (\Closure(mixed $route): ?array)|null                                     $routeIcons a route's icons, null for none
     */
    public function __construct(private readonly iterable|\Closure $adapters, private readonly ?\Closure $routeIcons = null)
    {
    }

    /** @return iterable<IconAdapterInterface> */
    public function getAdapters(): iterable
    {
        return $this->adapters instanceof \Closure ? ($this->adapters)() : $this->adapters;
    }

    public function iconify(null|string|array|EnumType|IconizeInterface $icon, array $attributes = [], bool $wrap = true): null|string|array
    {
        if (!$icon) {
            return $icon;
        }

        if ($icon instanceof IconizeInterface) {
            $icon = $icon->__iconize() ?? $icon->__iconizeStatic();
        } elseif (null !== $this->routeIcons && ($routeIcons = ($this->routeIcons)($icon))) {
            $icon = $routeIcons;
        }

        if (is_array($icon) && is_associative($icon)) {
            return array_map_recursive(fn($i) => $this->iconify($i, $attributes, false), $icon);
        }
        if (is_array($icon)) {
            $icon = array_filter(array_map(fn($i) => $this->iconify($i, $attributes, $wrap), $icon));
            if ($icon) {
                return array_merge(...$icon);
            }
        } else {
            foreach ($this->getAdapters() as $provider) {
                if ($provider->supports($icon)) {
                    $icon = $provider->iconify($icon, $attributes, $wrap);
                    return $wrap ? [$icon] : $icon;
                }
            }
        }

        if($icon == "fa-solid fa-question-circle") return [];
        return $this->iconify("fa-solid fa-question-circle", $attributes, $wrap) ?? null;
    }
}
