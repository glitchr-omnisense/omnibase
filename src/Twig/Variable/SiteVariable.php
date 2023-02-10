<?php

namespace Base\Twig\Variable;

use Base\Routing\RouterInterface;
use Base\Service\BaseService;
use Base\Service\LocalizerInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\MaternityUnitInterface;
use Base\Service\SitemapperInterface;
use Base\Service\TranslatorInterface;
use DateTime;
use Symfony\Component\Routing\Router;

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
     * @var MaternityUnit
     */
    protected $maternityUnit;
    /**
     * @var BaseService
     */
    protected $baseService;

    public function __construct(
        RouterInterface $router,
        SitemapperInterface $sitemapper,
        TranslatorInterface $translator,
        LocalizerInterface $localizer,
        BaseService $baseService,
        MaintenanceProviderInterface $maintenanceProvider,
        MaternityUnitInterface $maternityUnit)
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->baseService = $baseService;
        $this->localizer = $localizer;
        $this->maintenanceProvider = $maintenanceProvider;
        $this->maternityUnit = $maternityUnit;
        $this->sitemapper = $sitemapper;
    }

    public function index() { return $this->baseService->getIndexPage(); }
    public function meta     (array $meta = [], ?string $locale = null) : array
    {
        $locale ??= $this->localizer->getLocale();
        return array_merge($this->baseService->getMeta($locale), $meta);
    }

    public function map()    { return $this->sitemapper->getAlternates(); }

    public function title()  { return $this->baseService->getSite()["title"] ?? null; }
    public function slogan() { return $this->baseService->getSite()["slogan"] ?? null; }
    public function logo()   { return $this->baseService->getSite()["logo"]   ?? null; }

    public function scheme   (?string $locale = null) : ?string { return $this->router->getScheme($locale);    }
    public function host     (?string $locale = null) : ?string { return $this->router->getHost($locale);  }
    public function port     (?string $locale = null) : ?string { return $this->router->getPort($locale);  }
    public function domain   (?string $locale = null) : ?string { return $this->router->getDomain($locale);    }
    public function subdomain(?string $locale = null) : ?string { return $this->router->getSubdomain($locale); }
    public function base_dir (?string $locale = null) : string  { return $this->router->getBaseDir($locale);   }
    public function url      (?string $nameOrUrl = "", array $routeParameters = [],  ?string $locale = null) : string  { return $this->router->getUrl($nameOrUrl, $routeParameters, Router::ABSOLUTE_URL); }
    public function under_maintenance() : bool { return $this->maintenanceProvider->isUnderMaintenance(); }

    public function birthdate(?string $locale = null) : DateTime { return $this->maternityUnit->getBirthdate($locale); }
    public function is_born(?string $locale = null) : bool { return $this->maternityUnit->isBorn($locale); }
    public function age(?string $locale = null) : string { return $this->maternityUnit->getAge($locale); }

    public function execution_time() { return $this->baseService->getExecutionTime(); }
}
