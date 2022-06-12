<?php

namespace Base\Cache;

use Base\Service\IconProvider;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class IconWarmer implements CacheWarmerInterface
{
    public function __construct(IconProvider $iconProvider)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->iconProvider   = $iconProvider;
    }

    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli") echo " // Warming up cache... Icon provider".PHP_EOL.PHP_EOL;

        return [ get_class($this->iconProvider)];
    }
}