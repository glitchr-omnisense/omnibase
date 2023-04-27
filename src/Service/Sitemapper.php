<?php

namespace Base\Service;

use Base\Annotations\Annotation\Sitemap;
use Base\Annotations\AnnotationReader;
use Base\Exception\SitemapNotFoundException;
use Base\Routing\RouterInterface;
use Base\Service\Model\SitemapEntry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Router;
use Twig\Environment;

class Sitemapper implements SitemapperInterface
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var AnnotationReader
     */
    protected $annotationReader;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var LocalizerInterface
     */
    protected $localizer;

    /**
     * @var MimeTypes
     */
    protected $mimeTypes;

    private $computeFlag = true;
    protected string $hostname = "";
    protected array $urlset = [];

    public function __construct(Environment $twig, AnnotationReader $annotationReader, RouterInterface $router, LocalizerInterface $localizer)
    {
        $this->twig             = $twig;
        $this->router           = $router;
        $this->localizer   = $localizer;
        $this->annotationReader = $annotationReader;

        $this->mimeTypes        = new MimeTypes();
    }

    public function getSitemap(Route $route): ?Sitemap
    {
        $controller = $route->getDefault("_controller");
        if ($controller === null) {
            return null;
        }

        list($class, $method) = explode("::", $controller);
        if (!class_exists($class)) {
            return null;
        }

        $annotations = $this->annotationReader->getAnnotations($class, Sitemap::class, [AnnotationReader::TARGET_METHOD]);
        $annotations = $annotations[AnnotationReader::TARGET_METHOD][$class][$method] ?? [];

        $sitemap = end($annotations);
        return $sitemap === false ? null : $sitemap;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }
    public function setHostname(string $hostname): self
    {
        $this->hostname = $hostname;
        return $this;
    }

    public function register(string|Route $routeOrName, array $routeParameters = []): self
    {
        if (is_string($routeOrName)) {
            $route = $this->router->getRoute($routeOrName);
        } else {
            $route = $routeOrName;
        }

        $routeMatch = $this->router->getRouteMatch($route->getPath());
        if (!$routeMatch) {
            return $this;
        }

        $sitemap = $this->getSitemap($route);
        if (!$sitemap) {
            throw new SitemapNotFoundException("Sitemap annotation not found for \"".($routeMatch["_controller"] ?? $route->getPath())."\".");
        }

        $routeName     = $sitemap->getGroup() ?? $route->getDefaults()["_canonical_route"] ?? $routeMatch["_route"] ?? null;
        if (!$routeName) {
            return $this;
        }

        $this->computeFlag = false;

        $routeDefaults = $route->getDefaults();
        $routeParameters = array_filter($routeParameters, fn ($p) => !str_starts_with($p, "_"), ARRAY_FILTER_USE_KEY);
        if (array_key_exists("_locale", $routeDefaults)) {
            $routeParameters["_locale"] = $routeDefaults["_locale"];
        }

        $url = $this->router->generate($routeName, $routeParameters, Router::ABSOLUTE_URL);

        $sitemapEntry = new SitemapEntry($url);
        $sitemapEntry->setPriority($sitemap->getPriority());
        $sitemapEntry->setLastMod($sitemap->getLastMod());
        $sitemapEntry->setChangeFreq($sitemap->getChangeFreq());

        $locale = $this->localizer->getLocale($routeParameters["_locale"] ?? null);
        $sitemapEntry->setLocale($locale);

        $routeParameters = array_filter($routeParameters, fn ($p) => !str_starts_with($p, "_"), ARRAY_FILTER_USE_KEY);
        if (!array_key_exists($routeName.".".md5(serialize($routeParameters)), $this->urlset)) {
            $this->urlset[$routeName.".".md5(serialize($routeParameters))] = $sitemapEntry;
        }

        if (array_key_exists("_locale", $routeDefaults)) {
            $this->urlset[$routeName.".".md5(serialize($routeParameters))]->addAlternate($sitemapEntry);
        }

        return $this;
    }

    public function registerUrl(string $url): self
    {
        $routeParameters = $this->router->getRouteMatch($url);
        if (!$routeParameters) {
            throw new RouteNotFoundException("Route \"$url\" not found.");
        }

        return $this->register($routeParameters["_route"], $routeParameters);
    }

    public function registerAnnotations(): self
    {
        $this->computeFlag = false;
        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            $sitemap = $this->getSitemap($route);
            if (!$sitemap) {
                continue;
            }

            $routeParameters = $this->router->getRouteMatch($route->getPath()) ?? [];
            $numberOfParameters = count(array_filter($routeParameters, fn ($p) => !str_starts_with($p, "_"), ARRAY_FILTER_USE_KEY));
            if ($numberOfParameters > 0) {
                continue;
            }

            try {
                $this->register($route);
            } catch(\Base\Exception\SitemapNotFoundException $e) {
            }
        }

        return $this;
    }

    public function get(SitemapEntry|string|null $sitemapOrRouteName = null): ?SitemapEntry
    {
        $ret = null;

        $sitemapOrRouteName ??= $this->getSitemap($this->router->getRoute());
        if (!$sitemapOrRouteName) {
            return null;
        }

        array_map_recursive(function ($sitemap) use (&$ret, $sitemapOrRouteName) {
            if ($ret !== null) {
                return;
            }

            if ($sitemapOrRouteName instanceof SitemapEntry) {
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

        if ($sitemap === null) {
            return $sitemapOrRouteName instanceof SitemapEntry ? $sitemapOrRouteName->getAlternates() : null;
        }

        $urlset = $this->doCompute();
        foreach ($urlset as $entry) {
            if (!empty($alternates)) {
                break;
            }
            if ($entry === $sitemap) {
                $alternates = $sitemap->getAlternates();
                continue;
            }

            foreach ($entry->getAlternates() as $alternate) {
                if ($alternate->getLoc() === $entry->getLoc()) {
                    $alternates = $entry->getAlternates();
                    if (($pos = array_search($alternate, $alternates)) !== false) {
                        unset($alternates[$pos]);
                    }

                    array_unshift($alternates, $sitemap);
                }

                if (!empty($alternates)) {
                    break;
                }
            }
        }

        return array_unique($alternates);
    }

    protected function doCompute()
    {
        if ($this->computeFlag === false) {
            return $this->urlset;
        }

        $this->urlset = [];

        $entries = array_inflate(".", $this->urlset);
        foreach ($entries as $group => $entry) {
            if ($entry instanceof SitemapEntry) {
                $this->urlset[$group] = $entry;
                continue;
            }

            array_map_recursive(function ($sitemap) use ($group) {
                if (!array_key_exists($group, $this->urlset)) {
                    $urlset[$group] = $sitemap;
                }

                $this->urlset[$group]->addAlternate($sitemap);
            }, $entry);
        }

        return $this->urlset;
    }

    public function serve(string $name, array $context = []): Response
    {
        $urlset = array_reverse(array_map(fn ($s) => $s->toArray($this->hostname), $this->doCompute()));

        $extension = explode(".", basename($name, ".twig"));
        $extension = end($extension) ?? "txt";
        $mimeTypes = $this->mimeTypes->getMimeTypes($extension);

        $response = new Response(
            $this->twig->render(
                $name,
                array_merge($context, [
                    'urlset' => $urlset,
                    'hostname' => $this->hostname
                ])
            )
        );

        if ($mimeTypes) {
            $response->headers->set('Content-Type', first($mimeTypes));
        }

        return $response;
    }
}
