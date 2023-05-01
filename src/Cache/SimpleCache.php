<?php

namespace Base\Cache;

use Base\Cache\Abstract\AbstractLocalCache;
use DateInterval;

final class SimpleCache extends AbstractLocalCache implements SimpleCacheInterface
{
    public function warmUp(string $cacheDir): array
    {
        return [];
    }

    //
    // MEM Simple cache
    //
    public static function useSimpleCacheVersion3(): bool
    {
        return
            PHP_MAJOR_VERSION === 8 &&
            (new ReflectionClass(CacheInterface::class))->getMethod('get')->getReturnType() !== null;
    }

    public static function getMemoryCache(): CacheInterface
    {
        if (!self::$memcache) {
            self::$memcache = self::useSimpleCacheVersion3() ? new Memory\SimpleCache3() : new Memory\SimpleCache1();
        }

        return self::$memcache;
    }

    //
    // Adapter to PSR Simple Cache Interface
    //
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getCache();
    }
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->setCache($key, $value, $ttl);
    }
    public function delete(string $key): bool
    {
        return $this->deleteCache($key);
    }

    public function clear(): bool
    {
        return $this->deleteCache();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $multiple = [];
        foreach ($keys as $key) {
            $multiple[$key] = $this->getCache($key);
        }

        return $multiple;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $ret = true;
        foreach ($keys as $key) {
            $ret &= $this->delete($key);
        }

        return $ret;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $ret = true;
        foreach ($keys as $key) {
            $ret &= $this->delete($key);
        }

        return $ret;
    }

    public function has(string $key): bool
    {
        return $this->hasCache($key);
    }
}
