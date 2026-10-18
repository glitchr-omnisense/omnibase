<?php

namespace Base\Twig\Variable;

use Symfony\Component\Routing\Router;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\LauncherInterface;
use Base\Service\LocalizerInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\SettingBagInterface;
use Base\Service\SitemapperInterface;
use Base\Service\TranslatorInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class SiteVariable
{
    /**
     * @var AdvancedRouterInterface
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
     * The settings stored in the back office (title, slogan, logo, meta), read
     * where they were read through BaseService (plan "omnibase agnostique", C9:
     * BaseService is no longer built for every page that prints the site's name).
     */
    protected SettingBagInterface $settingBag;

    /** When the page started: the kernel's start (debug), else when this was built - BaseService's measure. */
    protected float $startTime;

    public function __construct(
        AdvancedRouterInterface      $router,
        SitemapperInterface          $sitemapper,
        TranslatorInterface          $translator,
        LocalizerInterface           $localizer,
        SettingBagInterface          $settingBag,
        MaintenanceProviderInterface $maintenanceProvider,
        LauncherInterface            $launcher,
        ?KernelInterface             $kernel = null
    )
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->settingBag = $settingBag;
        $startTime = $kernel?->getStartTime();
        $this->startTime = $startTime && is_finite($startTime) ? $startTime : microtime(true);
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
        return $this->router->getRouteIndex();
    }

    public function meta(array $meta = [], ?string $locale = null): array
    {
        $locale ??= $this->localizer->getLocale();

        return array_merge($this->settingBag->get("base.settings.meta", $locale) ?? [], $meta);
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
        $routeName = $this->router->getRouteName();
        return $routeName ? (explode('.', $this->router->getRouteName())[0] ?? null) : null;
    }

    /**
     * @return mixed|null
     */
    public function title()
    {
        return $this->settingBag->getScalar("base.settings.title");
    }

    /**
     * @return mixed|null
     */
    public function slogan()
    {
        return $this->settingBag->getScalar("base.settings.slogan");
    }

    /**
     * @return mixed|null
     */
    public function logo()
    {
        return $this->settingBag->getScalar("base.settings.logo");
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

    /**
     * Whether the visitor has not been seen within the last $within seconds,
     * remembered in the cookie USER/LAST_VISIT - which a visitor who refused
     * the site's own cookies (USER) is not given: such a visitor is a
     * newcomer at each page.
     */
    public function is_newcomer(int $within = 0): bool
    {
        $lastVisit = $_COOKIE['USER/LAST_VISIT'] ?? 0;
        if (!\Base\Security\CookieAnswer::refused('USER')) {
            setcookie('USER/LAST_VISIT', time(), time() + $within, '/', parse_url2(get_url())['domain'] ?? '');
        }

        return !$lastVisit;
    }

    /**
     * @return float
     */
    public function execution_time()
    {
        return round(microtime(true) - $this->startTime, 2);
    }
}
