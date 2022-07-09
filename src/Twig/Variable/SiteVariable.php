<?php

namespace Base\Twig\Variable;

use Base\Routing\RouterInterface;
use Base\Service\BaseService;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\MaternityServiceInterface;
use Base\Service\TranslatorInterface;
use DateTime;

class SiteVariable
{
    public function __construct(RouterInterface $router, TranslatorInterface $translator, BaseService $baseService, MaintenanceProviderInterface $maintenanceProvider,  MaternityServiceInterface $maternityService)
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->baseService = $baseService;
        
        $this->maintenanceProvider = $maintenanceProvider;
        $this->maternityService = $maternityService;
    }
    
    public function homepage() { return $this->baseService->getHomepage(); }
    public function meta     (?string $locale = null) : ?string { return $this->baseService->getMeta($locale); }

    public function title()  { return $this->baseService->getSite()["title"]  ?? null; }
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
