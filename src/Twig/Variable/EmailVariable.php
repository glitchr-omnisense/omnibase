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

    public function homepage() { return $this->baseService->getIndexPage(); }

    public function title()  { return $this->baseService->getEmail()["title"]  ?? null; }
    public function slogan() { return $this->baseService->getEmail()["slogan"] ?? null; }
    public function logo()   { return $this->baseService->getEmail()["logo"]   ?? null; }

    public function age(?string $locale = null) : string { return $this->maternityUnit->getAge($locale); }
}
