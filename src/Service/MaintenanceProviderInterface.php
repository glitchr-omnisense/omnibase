<?php

namespace Base\Service;

interface MaintenanceProviderInterface
{
    public function getRemainingTime(): int;

    public function getPercentage(): int;

    public function getDowntime(): int;

    public function getUptime(): int;

    public function isUnderMaintenance(): bool;
}
