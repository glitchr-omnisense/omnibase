<?php

namespace Base\Auth\Policy;

use Base\Service\LauncherInterface;

/**
 * LAUNCH_ACCESS (plan "omnibase agnostic", step F5e, slice 1): the site is launched, or the address is
 * one of the restriction's exceptions (Access::exception()).
 */
class Launch
{
    public const ATTRIBUTE = 'LAUNCH_ACCESS';

    public function __construct(private readonly LauncherInterface $launcher, private readonly Access $access)
    {
    }

    public function allows(mixed $subject): bool
    {
        return $this->launcher->isLaunched() || $this->access->exception($subject);
    }
}
