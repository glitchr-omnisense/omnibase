<?php

namespace Base\CacheWarmer;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class SpreadsheetCacheWarmer implements CacheWarmerInterface
{
    /** @var int */
    protected int $shellVerbosity;

    public function __construct() { $this->shellVerbosity = getenv("SHELL_VERBOSITY"); }

    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli")
            echo " // Warming up cache... PHP Spreadsheet".PHP_EOL.PHP_EOL;

        // Implement phpspreadsheet cache
        $psr6Cache = new FilesystemAdapter("phpspreadsheet");
        $psr16Cache = new Psr16Cache($psr6Cache);
        \PhpOffice\PhpSpreadsheet\Settings::setCache($psr16Cache);

        return [get_class($psr16Cache)];
    }
}