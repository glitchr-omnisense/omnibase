<?php

namespace Base\Request;

use Base\Service\Model\DevelopmentEnvironment;
use Omnibridge\Contract\Environment;

/**
 * Which page answers an error, written once for every host (plan "omnibase agnostic", step F5e, slice 5b; formerly
 * ErrorController::Main() and renderException()): for the site's developers, the debug page (RESCUE), unless the
 * error page itself is previewed (_preview_error); otherwise the site's error page - under /admin the back office's
 * own first (matched by the path, not the route: an address that matches nothing has no route, and a mistyped
 * /admin/... is the most common error there), then the site's.
 */
final class ErrorPage
{
    public const RESCUE = 'rescue';
    public const PREVIEW_ROUTE = '_preview_error';
    public const ADMIN_TEMPLATE = '@Admin/error.html.twig';
    public const TEMPLATE = 'exception.html.twig';

    public function __construct(private readonly Environment $environment)
    {
    }

    /** @return self::RESCUE|list<string> the debug page, or the templates to try in their order */
    public function choose(?string $route, string $path): string|array
    {
        if (DevelopmentEnvironment::is($this->environment) && self::PREVIEW_ROUTE !== $route) {
            return self::RESCUE;
        }

        return str_starts_with($path, '/admin') ? [self::ADMIN_TEMPLATE, self::TEMPLATE] : [self::TEMPLATE];
    }
}
