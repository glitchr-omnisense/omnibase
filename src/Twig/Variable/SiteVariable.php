<?php

namespace Base\Twig\Variable;

use Base\Routing\RouterInterface;
use Base\Service\BaseService;
use Base\Service\LocaleProviderInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\MaternityServiceInterface;
use Base\Service\SitemapperInterface;
use Base\Service\TranslatorInterface;
use DateTime;

class SiteVariable
{
    public function __construct(
        RouterInterface $router,
        SitemapperInterface $sitemapper,
        TranslatorInterface $translator,
        LocaleProviderInterface $localeProvider,
        BaseService $baseService,
        MaintenanceProviderInterface $maintenanceProvider,
        MaternityServiceInterface $maternityService)
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->baseService = $baseService;
        $this->localeProvider = $localeProvider;
        $this->maintenanceProvider = $maintenanceProvider;
        $this->maternityService = $maternityService;
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

    public function birthdate(?string $locale = null) : DateTime { return $this->maternityService->getBirthdate($locale); }
    public function is_born(?string $locale = null) : bool { return $this->maternityService->isBorn($locale); }
    public function age(?string $locale = null) : string { return $this->maternityService->getAge($locale); }

    public function execution_time() { return $this->baseService->getExecutionTime(); }
}
