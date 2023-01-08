<?php

namespace Base\CacheWarmer;

use Base\Service\SettingBag;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class SettingsCacheWarmer implements CacheWarmerInterface
{
    /**
     * @var SettingBag
     */
    protected $settingBag;
    
    protected int $shellVerbosity;

    public function __construct(SettingBag $settingBag)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->settingBag   = $settingBag;
    }

    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        return [];
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli")
            echo " // Warming up cache... Setting bag".PHP_EOL.PHP_EOL;

        return $this->settingBag->warmUp($cacheDir);
    }
}