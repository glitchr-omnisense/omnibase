<?php

namespace Base\Traits;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

trait SimpleCacheTrait
{
    public function __construct(string $cacheDir)
    {
        $cacheFile = $cacheDir."/simple_cache/".str_replace(['\\', '/'], ['__', '_'], static::class).".php";

        $this->setCache(new PhpArrayAdapter($cacheFile, new FilesystemAdapter()));
        $this->warmUp($cacheDir);
    }

    protected function getCacheKey(string $realClassName): string
    {
        return str_replace(['\\', '/'], ['__', '_'], $realClassName);
    }
    public function hasCache(string $key): bool
    {
        return $this->cache != null && $this->cache->hasItem($this->getCacheKey(static::class.$key));
    }

    public function getCache(string $key, mixed $fallback = null, $deferred = false): mixed
    {
        if($fallback !== null && !$this->hasCache($key))
                $this->setCache($key, is_callable($fallback) ? $fallback() : $fallback, $deferred);

        return $this->cache?->getItem($this->getCacheKey(static::class.$key))->get();
    }

    public function setCache(CacheItemPoolInterface|string $cacheOrKey, mixed $value = null, bool $deferred = false)
    {
        if($cacheOrKey instanceof CacheItemPoolInterface) {

            $this->cache = $cacheOrKey;
            return $this;
        }

        $item = $this->cache->getItem($this->getCacheKey(static::class.$cacheOrKey));
        $item->set($value);

        if($deferred) $this->cache->saveDeferred($item);
        else $this->cache->save($item);

        $this->saveDeferred |= $deferred;
        return $this;
    }

    public function commitCache()
    {
        if ($this->cache && $this->saveDeferred)
            $this->cache->commit();

        return $this;
    }
}