<?php

namespace Base\DependencyInjection;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class CacheWarmer implements CacheWarmerInterface
{
    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        // dump("BASE WARMUP..");
        return [];
    }
}