<?php

namespace Base\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Base\Traits\SimpleCacheTrait;

abstract class SimpleCache implements SimpleCacheInterface
{
    private bool $saveDeferred = false;
    private ?CacheItemPoolInterface $cache = null;
    use SimpleCacheTrait;

    public function __construct(string $cacheDir)
    {
        $cacheFile = $cacheDir."/simple_cache/".str_replace(['\\', '/'], ['__', '_'], static::class).".php";

        $this->setCache(new PhpArrayAdapter($cacheFile, new FilesystemAdapter()));
        $this->warmUp($cacheDir);
    }
}