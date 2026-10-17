<?php

namespace Base\Contract;

use Omnibridge\Twig\Contract\Urls as ViewUrls;

/**
 * Addresses, as omnibase's framework-free code asks for them: a route by its
 * name, as a path (`path()`, omnibridge/twig's) or whole, with the scheme and
 * the host (`url()`, for a mail, a feed, a link shared), and a public file
 * (`asset()`, omnibridge/twig's).
 *
 * It is omnibridge/twig's Urls and the absolute address beside it: a page
 * rendered by any framework asks the same contract, so one object answers
 * both (plan "omnibase agnostique", C4, D8). Under Symfony,
 * Base\Bridge\Symfony\RouterUrls: the router and the asset packages, the
 * same strings as Twig's path(), url() and asset().
 */
interface Urls extends ViewUrls
{
    /**
     * The address of a route, whole: scheme, host, port and path.
     *
     * @param array<string, mixed> $parameters
     */
    public function url(string $name, array $parameters = []): string;
}
