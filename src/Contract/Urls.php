<?php

namespace Base\Contract;

use Omnibridge\Twig\Contract\Urls as ViewUrls;

/**
 * Addresses, as omnibase's framework-free code asks for them: a route by its
 * name, as a path (`path()`) or whole, with the scheme and the host (`url()`,
 * for a mail, a feed, a link shared), and a public file (`asset()`).
 *
 * It is omnibridge/twig's Urls under omnibase's name: a page rendered by any
 * framework asks the same contract, so one object answers both (plan
 * "omnibase agnostique", C4, D8). omnibridge/twig has had url() since
 * d786505; this interface declared it on its own side before, and now only
 * extends it. Under Symfony, Base\Bridge\Symfony\RouterUrls: the router and
 * the asset packages, the same strings as Twig's path(), url() and asset().
 *
 * Base\Contract\Link::to() reaches it with a router's reference type, as the
 * entities' __toLink() take one.
 */
interface Urls extends ViewUrls
{
}
