<?php

namespace Base\Request;

use Base\Auth\Account\Outcome;
use Base\Database\Repository\Repositories;
use Base\Entity\Layout\ShortLink;

/**
 * A short link followed (s.<host>/{slug}), written once for every host (plan "omnibase agnostic", step F5e, slice
 * 5b; formerly ShortLinkController::Main()): to the address it stands for, no such link no such page.
 *
 * Fixed on the way: the controller handed the address to a router method that does not exist (format()), so every
 * short link that existed answered 500; it goes to its address as it is written.
 */
final class ShortLinkRedirect
{
    public function __construct(private readonly Repositories $repositories)
    {
    }

    public function follow(string $slug): Outcome
    {
        $link = $this->repositories->of(ShortLink::class)->findOneBySlug($slug);
        $url = $link instanceof ShortLink ? (string) $link->getUrl() : '';

        return '' !== $url ? Outcome::url($url) : Outcome::missing("Page requested doesn't exist.");
    }
}
