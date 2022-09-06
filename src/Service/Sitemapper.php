<?php

namespace Base\Service;

use Base\Annotations\Annotation\Sitemap;
use Base\Annotations\AnnotationReader;
use Base\Response\XmlResponse;
use Base\Routing\RouterInterface;
use Base\Service\Model\SitemapEntry;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Router;
use Twig\Environment;

class Sitemapper implements SitemapperInterface
{
    private $computeFlag = true;
    protected string $hostname = "";
    protected array $urlset = [];

    public function __construct(Environment $twig, AnnotationReader $annotationReader, RouterInterface $router, LocaleProviderInterface $localeProvider)
    {
        $this->twig             = $twig;
        $this->router           = $router;
        $this->localeProvider   = $localeProvider;
        $this->annotationReader = $annotationReader;
    }

    public function getHostname(): string { return $this->hostname; }
    public function setHostname(string $hostname): self
    {
        $this->hostname = $hostname;
        return $this;
    }

    public function register(string $group, array $routeParameters): self
    {
        $this->computeFlag = false;
        return $this;
    }

    public function registerUrl(string $group, string $url): self
    {
        $this->computeFlag = false;
        return $this;
    }

    public function getSitemap(Route $route): ?Sitemap
    {
        $controller = $route->getDefault("_controller");
        if($controller === null) return null;

        list($class, $method) = explode("::", $controller);
        if(!class_exists($class)) return null;

        $annotations = $this->annotationReader->getAnnotations($class, Sitemap::class, [AnnotationReader::TARGET_METHOD]);
        $annotations = $annotations[AnnotationReader::TARGET_METHOD][$class][$method] ?? [];

        $sitemap = end($annotations);
        return $sitemap === false ? null : $sitemap;
    }

    public function registerAnnotations(): self
    {
        $this->computeFlag = false;
        foreach($this->router->getRouteCollection() as $routeName => $route)
        {
            $sitemap = $this->getSitemap($route);
            if(!$sitemap) continue;

            $routeParameters = $route->getDefaults();
            $numberOfParameters = count(array_filter($routeParameters, fn($p) => !str_starts_with($p, "_"), ARRAY_FILTER_USE_KEY));
            if($numberOfParameters == 0) {

                $url = $this->router->generate($routeName, $routeParameters, Router::ABSOLUTE_URL);

                $sitemapEntry = new SitemapEntry($url);
                $sitemapEntry->setPriority($sitemap->getPriority());
                $sitemapEntry->setLastMod($sitemap->getLastMod());
                $sitemapEntry->setChangeFreq($sitemap->getChangeFreq());
                $sitemapEntry->setLocale($routeParameters["_locale"] ?? $this->localeProvider->getLocale());

                $this->urlset[$sitemap->getGroup() ?? $routeName] = $sitemapEntry;
            }
        }

        return $this;
    }

    public function get(SitemapEntry|string|null $sitemapOrRouteName = null): ?SitemapEntry
    {
        $ret = null;

        $sitemapOrRouteName ??= $this->getSitemap($this->router->getRoute());
        if(!$sitemapOrRouteName) return null;

        array_map_recursive(function($sitemap) use (&$ret, $sitemapOrRouteName) {

            if($ret !== null) return;

            if($sitemapOrRouteName instanceof SitemapEntry) {
                $ret = $sitemapOrRouteName === $sitemap ? $sitemap : null;
                return;
            }

            $ret = $sitemap;

        }, is_string($sitemapOrRouteName) ? $this->urlset[explode(".", $sitemapOrRouteName)[0]] ?? [] : $this->urlset);

        return $ret;
    }

    public function getAlternates(SitemapEntry|string|null $sitemapOrRouteName = null)
    {
        $alternates = [];

        $sitemapOrRouteName ??= $this->router->getRouteName();
        $sitemap = $this->get($sitemapOrRouteName);

        if($sitemap === null)
            return $sitemapOrRouteName instanceof SitemapEntry ? $sitemapOrRouteName->getAlternates() : null;

        $urlset = $this->doCompute();
        foreach($urlset as $entry) {

            if(!empty($alternates)) break;
            if($entry === $sitemap) {

                $alternates = $sitemap->getAlternates();
                continue;
            }

            foreach($entry->getAlternates() as $alternate) {

                if ($alternate->getLoc() === $entry->getLoc()) {

                    $alternates = $entry->getAlternates();
                    if( ($pos = array_search($alternate, $alternates)) !== false)
                        unset($alternates[$pos]);

                    array_unshift($alternates, $sitemap);
                }

                if(!empty($alternates)) break;
            }
        }

        return array_unique($alternates);
    }

    protected function doCompute()
    {
        if($this->computeFlag === false)
            return $this->urlset;

        $this->urlset = [];

        $entries = array_inflate(".",$this->urlset);
        foreach($entries as $group => $entry) {

            if($entry instanceof SitemapEntry) {

                $this->urlset[$group] = $entry;
                continue;
            }

            array_map_recursive(function($sitemap) use ($group) {

                if (!array_key_exists($group, $this->urlset))
                    $urlset[$group] = $sitemap;

                $this->urlset[$group]->addAlternate($sitemap);

            }, $entry);
        }
        return $this->urlset;
    }
    public function generate(string $name, array $context = []): XmlResponse
    {
        $urlset = array_map(fn($s) => $s->toArray($this->hostname), $this->doCompute());

        return new XmlResponse($this->twig->render($name,
            array_merge($context, [
                'urlset' => $urlset,
                'hostname' => $this->hostname
            ]))
        );
    }
}
