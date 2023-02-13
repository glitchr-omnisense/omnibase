<?php

namespace Base\Twig\Variable;

use Base\Service\BaseService;
use Base\Service\MaternityUnitInterface;

class EmailVariable
{
    /**
     * @var MaternityUnit
     */
    protected $maternityUnit;

    /**
     * @var BaseService
     */
    protected $baseService;

    public function __construct(
        BaseService $baseService,
        MaternityUnitInterface $maternityUnit)
    {
        $this->baseService = $baseService;
        $this->maternityUnit = $maternityUnit;
    }

    public function homepage() { return $this->baseService->getRouteIndex(); }

    public function title() :?string { return $this->baseService->getEmail()["title"]  ?? null; }
    public function slogan():?string { return $this->baseService->getEmail()["slogan"] ?? null; }
    public function logo()  :?string { return $this->baseService->getEmail()["logo"]   ?? null; }
    public function address(?string $locale = null):?string { return $this->baseService->getEmail()["address"] ?? null; }

    public function age(?string $locale = null) : string { return $this->maternityUnit->getAge($locale); }
}
