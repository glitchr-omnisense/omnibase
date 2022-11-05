<?php

namespace Base\Twig\Variable;

use Base\Service\BaseService;
use Base\Service\MaternityServiceInterface;

class EmailVariable
{
    public function __construct(
        BaseService $baseService,
        MaternityServiceInterface $maternityService)
    {
        $this->baseService = $baseService;
        $this->maternityService = $maternityService;
    }

    public function homepage() { return $this->baseService->getIndexPage(); }

    public function title()  { return $this->baseService->getEmail()["title"]  ?? null; }
    public function slogan() { return $this->baseService->getEmail()["slogan"] ?? null; }
    public function logo()   { return $this->baseService->getEmail()["logo"]   ?? null; }

    public function age(?string $locale = null) : string { return $this->maternityService->getAge($locale); }
}
