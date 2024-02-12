<?php

namespace Base\Traits;

use DateInterval;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

/**
 *
 */
trait SimpleCacheTrait
{
    public function __construct(string $cacheDir, ?string $buildDir = null)
    {
        $phpCacheFile = $cacheDir . "/pools/simple/php/" . str_replace(['\\', '/'], ['__', '_'], static::class) . ".php";
        $fsCacheFile = $cacheDir . "/pools/simple/fs/" . str_replace(['\\', '/'], ['__', '_'], static::class);

        $phpArrayAdapter = new PhpArrayAdapter($phpCacheFile, new FilesystemAdapter('', 0, $fsCacheFile));
        $this->setCache($phpArrayAdapter);

        $this->warmUp($cacheDir, $buildDir);
    }

    protected function getCacheKey(string $realClassName): string
    {
        return str_replace(['\\', '/'], ['__', '___'], $realClassName);
    }

    public function hasCache(string $key): bool
    {
        return $this->cache != null && $this->cache->hasItem($this->getCacheKey(static::class . $key));
    }

    public function deleteCache(?string $key = null): bool
    {
        if ($key == null) {
            return $this->cache?->clear() ?? false;
        }

        return $this->cache?->deleteItem($this->getCacheKey(static::class . $key)) ?? false;
    }

    public function getCacheAdapter(): mixed
    {
        return $this->cache;
    }

    public function invalidateCache(?string $key = null): self
    {
        if ($key === null) {
            return $this->cache;
        }

        $this->cache?->deleteItem($this->getCacheKey(static::class . $key));
        return $this;
    }

    /**
     * @param string|null $key
     * @param mixed|null $fallback
     * @param int|DateInterval|null $ttl
     * @param $deferred
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getCache(?string $key = null, mixed $fallback = null, int|DateInterval|null $ttl = null, $deferred = false): mixed
    {
        if ($key === null) {
            return $this->cache;
        }

        if (!$this->hasCache($key)) {
            if ($fallback === null) {
                return null;
            }
            $this->setCache($key, is_callable($fallback) ? $fallback() : $fallback, $ttl, $deferred);
        }

        return $this->cache?->getItem($this->getCacheKey(static::class . $key))->get();
    }

    /**
     * @param CacheItemPoolInterface|string $cacheOrKey
     * @param mixed|null $value
     * @param int|DateInterval|null $ttl
     * @param bool $deferred
     * @return $this
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function setCache(CacheItemPoolInterface|string $cacheOrKey, mixed $value = null, int|DateInterval|null $ttl = null, bool $deferred = false)
    {
        if ($cacheOrKey instanceof CacheItemPoolInterface) {
            $this->cache = $cacheOrKey;
            return $this;
        }

        $item = $this->cache->getItem($this->getCacheKey(static::class . $cacheOrKey));
        $item->set($value);
        $item->expiresAfter($ttl);

        if ($deferred) {
            $this->cache->saveDeferred($item);
        } else {
            $this->cache->save($item);
        }

        $this->saveDeferred |= $deferred;
        return $this;
    }

    /**
     * @return $this
     */
    public function commitCache()
    {
        if ($this->cache && $this->saveDeferred) {
            $this->cache->commit();
        }

        return $this;
    }

    public function executeOnce(callable $fn, int|DateInterval|null $ttl = null): mixed
    {
        $keyCache = "/ExecuteOnce/" . callable_hash($fn);
        return $this->getCache($keyCache, $fn, $ttl);
    }
}
