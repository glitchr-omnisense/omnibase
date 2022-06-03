<?php

namespace Base\DependencyInjection;

use Base\Service\IconProvider;
use Base\Service\LocaleProvider;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CacheWarmer implements CacheWarmerInterface
{
    public function __construct(LocaleProvider $localeProvider, IconProvider $iconProvider)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->localeProvider = $localeProvider;
        $this->iconProvider   = $iconProvider;
    }

    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli") echo " // Warming up cache... Base bundle".PHP_EOL.PHP_EOL;

        $psr6Cache = new FilesystemAdapter("phpspreadsheet");
        $psr16Cache = new Psr16Cache($psr6Cache);
        \PhpOffice\PhpSpreadsheet\Settings::setCache($psr16Cache);

        return [get_class($this->localeProvider), get_class($this->iconProvider), get_class($psr16Cache)];
    }
}