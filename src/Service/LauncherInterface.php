<?php

namespace Base\Service;

use DateTime;

interface LauncherInterface
{
    public function getLaunchdate(?string $locale = null): ?DateTime;

    public function isLaunched(?string $locale = null): ?bool;

    public function since(?string $locale = null): string;
}
