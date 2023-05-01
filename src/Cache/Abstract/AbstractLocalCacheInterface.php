<?php

namespace Base\Cache\Abstract;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;

interface AbstractLocalCacheInterface extends WarmableInterface
{
    public function hasCache(string $key): bool;

    public function deleteCache(?string $key = null): bool;

    /**
     * @param string|null $key
     * @param mixed|null $fallback
     * @param int|\DateInterval|null $ttl
     * @param $deferred
     * @return mixed
     */
    public function getCache(?string $key = null, mixed $fallback = null, int|\DateInterval|null $ttl = null, $deferred = false): mixed;

    /**
     * @param CacheItemPoolInterface|string $cacheOrKey
     * @param mixed|null $value
     * @param int|\DateInterval|null $ttl
     * @param bool $deferred
     * @return mixed
     */
    public function setCache(CacheItemPoolInterface|string $cacheOrKey, mixed $value = null, int|\DateInterval|null $ttl = null, bool $deferred = false);

    /**
     * @param callable $fn
     * @param int|\DateInterval|null $ttl
     * @return mixed
     */
    public function executeOnce(callable $fn, int|\DateInterval|null $ttl = null);

    /**
     * @return mixed
     */
    public function commitCache();
}
