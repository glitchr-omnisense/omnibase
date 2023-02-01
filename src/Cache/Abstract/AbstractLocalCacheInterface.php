<?php

namespace Base\Cache\Abstract;

use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;

interface AbstractLocalCacheInterface extends WarmableInterface
{
    public function hasCache(string $key) : bool;
    public function deleteCache(?string $key = null) : bool;
    public function getCache(?string $key = null, mixed $fallback = null, int|\DateInterval|null $ttl = null, $deferred = false): mixed ;
    public function setCache(CacheItemPoolInterface|string $cacheOrKey, mixed $value = null, int|\DateInterval|null $ttl = null, bool $deferred = false);
    
    public function executeOnce(callable $fn, int|\DateInterval|null $ttl = null);
    public function commitCache();
}