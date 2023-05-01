<?php

namespace Base\Twig\Variable;

use Base\Service\BaseService;
use Base\Service\LauncherInterface;

/**
 *
 */
class EmailVariable
{
    protected LauncherInterface $launcher;

    protected BaseService $baseService;

    public function __construct(
        BaseService       $baseService,
        LauncherInterface $launcher
    )
    {
        $this->baseService = $baseService;
        $this->launcher = $launcher;
    }

    /**
     * @return string
     */
    public function homepage()
    {
        return $this->baseService->getRouteIndex();
    }

    public function title(): ?string
    {
        return $this->baseService->getEmail()['title'] ?? null;
    }

    public function slogan(): ?string
    {
        return $this->baseService->getEmail()['slogan'] ?? null;
    }

    public function logo(): ?string
    {
        return $this->baseService->getEmail()['logo'] ?? null;
    }

    public function address(?string $locale = null): ?string
    {
        return $this->baseService->getEmail()['address'] ?? null;
    }

    public function age(?string $locale = null): string
    {
        return $this->launcher->since($locale);
    }
}
