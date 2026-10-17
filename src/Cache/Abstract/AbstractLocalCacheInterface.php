<?php

namespace Base\Cache\Abstract;

use Psr\Cache\CacheItemPoolInterface;

/**
 * A service's own cache, warmed by omnibase's cache warmers (Base\Cache\Warmer\*,
 * which call warmUp()). warmUp() is declared here, with the signature of
 * HttpKernel's WarmableInterface, which this interface no longer extends
 * (plan "omnibase agnostique", C6): the AttributeReader and the
 * ClassMetadataManipulator - what an entity manager built by hand reads its
 * mapping through - load nothing of the framework. Symfony asks
 * WarmableInterface of the kernel, its router and its translator only, none of
 * which is one of these.
 */
interface AbstractLocalCacheInterface
{
    /**
     * Warms up the cache.
     *
     * @return string[] A list of classes or files to preload
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array;

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
