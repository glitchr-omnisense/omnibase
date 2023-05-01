<?php

namespace Base\Twig\Variable;

use Base\Routing\RouterInterface;
use Base\Service\BaseService;
use Base\Service\LauncherInterface;
use Base\Service\LocalizerInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\SitemapperInterface;
use Base\Service\TranslatorInterface;
use Symfony\Component\Routing\Router;

/**
 *
 */
class SiteVariable
{
    /**
     * @var Router
     */
    protected $router;
    /**
     * @var Sitemapper
     */
    protected $sitemapper;
    /**
     * @var Translator
     */
    protected $translator;
    /**
     * @var Localizer
     */
    protected $localizer;
    /**
     * @var MaintenanceProvider
     */
    protected $maintenanceProvider;
    /**
     * @var Launcher
     */
    protected $launcher;
    /**
     * @var BaseService
     */
    protected $baseService;

    public function __construct(
        RouterInterface              $router,
        SitemapperInterface          $sitemapper,
        TranslatorInterface          $translator,
        LocalizerInterface           $localizer,
        BaseService                  $baseService,
        MaintenanceProviderInterface $maintenanceProvider,
        LauncherInterface            $launcher
    )
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->baseService = $baseService;
        $this->localizer = $localizer;
        $this->maintenanceProvider = $maintenanceProvider;
        $this->launcher = $launcher;
        $this->sitemapper = $sitemapper;
    }

    /**
     * @return string
     */
    public function index()
    {
        return $this->baseService->getRouteIndex();
    }

    public function meta(array $meta = [], ?string $locale = null): array
    {
        $locale ??= $this->localizer->getLocale();

        return array_merge($this->baseService->getMeta($locale), $meta);
    }

    /**
     * @return mixed
     */
    public function map()
    {
        return $this->sitemapper->getAlternates();
    }

    /**
     * @return string|null
     */
    public function route()
    {
        return explode('.', $this->router->getRouteName())[0] ?? null;
    }

    /**
     * @return mixed|null
     */
    public function title()
    {
        return $this->baseService->getSite()['title'] ?? null;
    }

    /**
     * @return mixed|null
     */
    public function slogan()
    {
        return $this->baseService->getSite()['slogan'] ?? null;
    }

    /**
     * @return mixed|null
     */
    public function logo()
    {
        return $this->baseService->getSite()['logo'] ?? null;
    }

    public function scheme(?string $locale = null): ?string
    {
        return $this->router->getScheme($locale);
    }

    public function host(?string $locale = null): ?string
    {
        return $this->router->getHost($locale);
    }

    public function port(?string $locale = null): ?string
    {
        return $this->router->getPort($locale);
    }

    public function domain(?string $locale = null): ?string
    {
        return $this->router->getDomain($locale);
    }

    public function subdomain(?string $locale = null): ?string
    {
        return $this->router->getSubdomain($locale);
    }

    public function base_dir(?string $locale = null): string
    {
        return $this->router->getBaseDir($locale);
    }

    public function url(?string $nameOrUrl = '', array $routeParameters = [], ?string $locale = null): string
    {
        return $this->router->getUrl($nameOrUrl, $routeParameters, Router::ABSOLUTE_URL);
    }

    public function under_maintenance(): bool
    {
        return $this->maintenanceProvider->isUnderMaintenance();
    }

    public function launchdate(?string $locale = null): \DateTime
    {
        return $this->launcher->getLaunchdate($locale);
    }

    public function is_launchedd(?string $locale = null): bool
    {
        return $this->launcher->isLaunched($locale);
    }

    public function age(?string $locale = null): string
    {
        return $this->launcher->since($locale);
    }

    public function is_newcomer(int $within = 0): bool
    {
        $lastVisit = $_COOKIE['USER/LAST_VISIT'] ?? 0;
        setcookie('USER/LAST_VISIT', time(), time() + $within, '/', parse_url2(get_url())['domain'] ?? '');

        return !$lastVisit;
    }

    /**
     * @return float
     */
    public function execution_time()
    {
        return $this->baseService->getExecutionTime();
    }
}
