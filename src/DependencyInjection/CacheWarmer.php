<?php

namespace Base\DependencyInjection;

use Base\Service\IconProvider;
use Base\Service\LocaleProvider;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class CacheWarmer implements CacheWarmerInterface
{
    public function __construct(LocaleProvider $localeProvider, IconProvider $iconProvider)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->localeProvider = $localeProvider;
        $this->iconProvider = $iconProvider;
    }

    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli") echo " // Warming up cache... Base bundle".PHP_EOL.PHP_EOL;

        return [get_class($this->localeProvider), get_class($this->iconProvider)];
    }
}