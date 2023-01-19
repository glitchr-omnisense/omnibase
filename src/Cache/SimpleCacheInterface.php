<?php

namespace Base\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;

interface SimpleCacheInterface extends WarmableInterface
{

    public function hasCache(string $key) : bool;
    public function getCache(string $key, mixed $fallback = null, $deferred = false): mixed ;
    public function setCache(CacheItemPoolInterface|string $cacheOrKey, mixed $value = null, bool $deferred = false);
    
    public function executeOnce(callable $fn);
    public function commitCache();
}