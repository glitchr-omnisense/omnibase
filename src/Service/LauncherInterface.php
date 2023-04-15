<?php

namespace Base\Service;

use DateTime;
use Symfony\Component\HttpKernel\Event\RequestEvent;

interface LauncherInterface
{
    public function getLaunchdate(?string $locale = null): ?DateTime;
    public function isLaunched(?string $locale = null): ?bool;
    public function since(?string $locale = null): string;

    public function redirectOnDeny(?RequestEvent $event = null): bool;
}
