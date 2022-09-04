<?php

namespace Base\CacheWarmer;

use Base\Service\LocaleProvider;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class LocaleCacheWarmer implements CacheWarmerInterface
{
    public function __construct(LocaleProvider $localeProvider)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->localeProvider = $localeProvider;
    }

    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli")
            echo " // Warming up cache... Locale bundle".PHP_EOL.PHP_EOL;

        return [get_class($this->localeProvider)];
    }
}