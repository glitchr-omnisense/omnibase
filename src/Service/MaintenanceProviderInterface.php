<?php

namespace Base\Service;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 *
 */
interface MaintenanceProviderInterface
{
    public function getRemainingTime(): int;

    public function getPercentage(): int;

    public function getDowntime(): int;

    public function getUptime(): int;

    public function isUnderMaintenance(): bool;

    public function redirectOnDeny(?RequestEvent $event = null): bool;
}
