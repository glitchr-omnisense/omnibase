<?php

namespace Base\View;

use Omnibridge\Contract\Views;
use Omnibridge\Twig\TwigViews;
use Twig\Environment;

/**
 * omnibridge's Views from what a constructor was given (plan "omnibase
 * agnostic", step F5a): the core's services render through Views, and still
 * accept Twig's environment, deprecated until 6.0, wrapped in omnibridge/twig's
 * TwigViews. Naming Twig's class in a type loads nothing of Twig.
 */
final class ViewsAdapter
{
    public static function of(Views|Environment $views): Views
    {
        if ($views instanceof Views) {
            return $views;
        }
        if (!class_exists(TwigViews::class)) {
            throw new \LogicException('Twig\'s environment was given where omnibridge\'s Views is asked, and omnibridge/twig (its TwigViews) is not installed.');
        }
        @trigger_error('Since omnisense/omnibase 4.x: giving Twig\'s environment to a service of the core is deprecated, give omnibridge\'s Views (omnibridge/twig\'s TwigViews over it).', \E_USER_DEPRECATED);

        return new TwigViews($views);
    }

    public static function ofNullable(Views|Environment|null $views): ?Views
    {
        return null === $views ? null : self::of($views);
    }
}
