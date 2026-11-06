<?php

namespace Base\Request;

use Base\Event\SitemapEvent;
use Base\Service\SitemapperInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The sitemap (/sitemap.xml, /sitemap.txt), written once for every host (plan "omnibase agnostic", step F5e, slice
 * 5b; formerly SitemapController::Main()): the pages the routes' #[Sitemap] declare, on the request's origin, then
 * those the application enumerates itself (its entities' addresses), asked through SitemapEvent::BUILD; the host
 * serves the template of the extension asked.
 */
final class SitemapPage
{
    public const EXTENSIONS = ['xml', 'txt'];

    public function __construct(
        private readonly SitemapperInterface $sitemapper,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function build(string $origin): SitemapperInterface
    {
        // registerAttributes(), not registerMetadata(): the method was renamed with the rest of the Annotation* ->
        // Attribute* move, and every /sitemap.xml answered "Call to undefined method" before.
        $this->sitemapper->setHostname($origin)->registerAttributes();
        // Attributes only cover parameterless routes: entity addresses are the application's to enumerate. The
        // event's name as Symfony's dispatcher takes it (a PSR-14 dispatcher reads the event alone).
        $this->dispatcher?->dispatch(new SitemapEvent($this->sitemapper, $origin), SitemapEvent::BUILD);

        return $this->sitemapper;
    }

    public static function template(string $extension): string
    {
        return 'sitemap.'.$extension.'.twig';
    }
}
