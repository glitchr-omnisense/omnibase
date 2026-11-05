<?php

namespace Base\Auth\Policy;

use Base\Service\MaintenanceProviderInterface;

/**
 * MAINTENANCE_ACCESS (plan "omnibase agnostic", step F5e, slice 1): the site is not under maintenance,
 * or the address is one of the restriction's exceptions (Access::exception()).
 */
class Maintenance
{
    public const ATTRIBUTE = 'MAINTENANCE_ACCESS';

    public function __construct(private readonly MaintenanceProviderInterface $maintenanceProvider, private readonly Access $access)
    {
    }

    public function allows(mixed $subject): bool
    {
        return !$this->maintenanceProvider->isUnderMaintenance() || $this->access->exception($subject);
    }
}
