<?php

namespace Base\Cache;

use Base\Service\BaseService;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class BaseWarmer implements CacheWarmerInterface
{
    public function __construct(BaseService $baseService)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->baseService = $baseService;
    }

    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli") echo " // Warming up cache... Locale bundle".PHP_EOL.PHP_EOL;

        return [get_class($this->baseService)];
    }
}