<?php

namespace Base\Service;

/**
 * Adds to the sitemap what #[Sitemap] cannot reach on its own.
 *
 * Sitemapper::registerAttributes() can only list a route with no parameters,
 * because it has no way to know what to put in them. Everything behind a
 * parameter - an article's slug, a shop's products, a forum's categories -
 * comes from an application or a bundle implementing this interface, which
 * is autoconfigured (tag "base.sitemap_provider"):
 *
 *     class ArticleSitemapProvider implements SitemapProviderInterface
 *     {
 *         public function provide(SitemapperInterface $sitemapper): void
 *         {
 *             foreach ($this->articles->findPublished() as $article) {
 *                 $sitemapper->register('app_article', ['slug' => $article->getSlug()]);
 *             }
 *         }
 *     }
 *
 * The route still needs its #[Sitemap] attribute: that is where the entry's
 * priority and change frequency are read from.
 */
interface SitemapProviderInterface
{
    public function provide(SitemapperInterface $sitemapper): void;
}
