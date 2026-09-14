<?php

namespace Base\Event;

use Base\Service\SitemapperInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched while /sitemap.xml is being built, once the routes carrying a
 * Sitemap attribute have been registered.
 *
 * registerAttributes() can only see routes it can generate on its own, so it
 * skips every route taking a parameter - which is every entity page a site
 * actually wants indexed. The bundle has no way to know what those entities
 * are, and must not: listen to this event from the application and call
 * register() or registerUrl() for the URLs it alone can enumerate.
 */
class SitemapEvent extends Event
{
    public const BUILD = 'sitemap.build';

    public function __construct(private readonly SitemapperInterface $sitemapper, private readonly string $hostname)
    {
    }

    public function getSitemapper(): SitemapperInterface
    {
        return $this->sitemapper;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }
}
