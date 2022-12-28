<?php

namespace Base\Twig\Variable;

use Base\Routing\RouterInterface;
use Base\Service\BaseService;
use Base\Service\LocaleProviderInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\MaternityUnitInterface;
use Base\Service\SitemapperInterface;
use Base\Service\TranslatorInterface;
use DateTime;

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
     * @var LocaleProvider
     */
    protected $localeProvider;
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
        LocaleProviderInterface $localeProvider,
        BaseService $baseService,
        MaintenanceProviderInterface $maintenanceProvider,
        MaternityUnitInterface $maternityUnit)
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->baseService = $baseService;
        $this->localeProvider = $localeProvider;
        $this->maintenanceProvider = $maintenanceProvider;
        $this->maternityUnit = $maternityUnit;
        $this->sitemapper = $sitemapper;
    }

    public function index() { return $this->baseService->getIndexPage(); }
    public function meta     (array $meta = [], ?string $locale = null) : array
    {
        $locale ??= $this->localeProvider->getLocale();
        return array_merge($this->baseService->getMeta($locale), $meta);
    }

    public function map()    { return $this->sitemapper->getAlternates(); }

    public function title()  { return $this->baseService->getSite()["title"] ?? null; }
    public function slogan() { return $this->baseService->getSite()["slogan"] ?? null; }
    public function logo()   { return $this->baseService->getSite()["logo"]   ?? null; }

    public function scheme   (?string $locale = null) : ?string { return $this->router->getScheme($locale);    }
    public function host     (?string $locale = null) : ?string { return $this->router->getHostname($locale);  }
    public function domain   (?string $locale = null) : ?string { return $this->router->getDomain($locale);    }
    public function subdomain(?string $locale = null) : ?string { return $this->router->getSubdomain($locale); }
    public function base_dir (?string $locale = null) : string  { return $this->router->getBaseDir($locale);   }

    public function under_maintenance() : bool { return $this->maintenanceProvider->isUnderMaintenance(); }

    public function birthdate(?string $locale = null) : DateTime { return $this->maternityUnit->getBirthdate($locale); }
    public function is_born(?string $locale = null) : bool { return $this->maternityUnit->isBorn($locale); }
    public function age(?string $locale = null) : string { return $this->maternityUnit->getAge($locale); }

    public function execution_time() { return $this->baseService->getExecutionTime(); }
}
