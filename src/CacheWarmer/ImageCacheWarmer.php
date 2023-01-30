<?php

namespace Base\CacheWarmer;

use Base\Service\SettingBag;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class ImageCacheWarmer implements CacheWarmerInterface
{
    protected int $shellVerbosity;

    public function __construct(SettingBag $settingBag)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
    }

    public function isOptional() : bool { return true; }
    public function warmUp($cacheDir): array
    {
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli")
            echo " // Warming up cache... Prepare database image".PHP_EOL.PHP_EOL;

        return $this->settingBag->warmUp($cacheDir);
    }
}