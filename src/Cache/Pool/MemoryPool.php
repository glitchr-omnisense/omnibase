<?php

namespace Base\Cache\Pool;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A PSR-6 pool held in memory, for the core's local caches (Base\Cache\Abstract\AbstractLocalCache:
 * the obfuscator's map, the icons...) where no pool is given and symfony/cache is not installed
 * (since 5.0, plan "omnibase agnostic", step F6: the core requires psr/cache, not symfony/cache).
 * What it holds lasts as long as the process; a host that wants it on disk gives a pool of its own,
 * as omnibridge/symfony's configuration does (symfony/cache's PhpArrayAdapter, as before).
 *
 * The parameters are left untyped so that it implements psr/cache 1, 2 and 3 alike.
 */
final class MemoryPool implements CacheItemPoolInterface
{
    /** @var array<string, MemoryItem> */
    private array $items = [];

    /** @var array<string, MemoryItem> */
    private array $deferred = [];

    public function getItem($key): CacheItemInterface
    {
        $key = (string) $key;
        $item = $this->deferred[$key] ?? $this->items[$key] ?? null;
        if (null !== $item && $item->expired()) {
            unset($this->items[$key], $this->deferred[$key]);
            $item = null;
        }

        return null === $item ? new MemoryItem($key) : $item->hit();
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[(string) $key] = $this->getItem($key);
        }

        return $items;
    }

    public function hasItem($key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        $this->items = $this->deferred = [];

        return true;
    }

    public function deleteItem($key): bool
    {
        unset($this->items[(string) $key], $this->deferred[(string) $key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof MemoryItem) {
            return false;
        }
        unset($this->deferred[$item->getKey()]);
        $this->items[$item->getKey()] = $item;

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof MemoryItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    public function commit(): bool
    {
        $this->items = $this->deferred + $this->items;
        $this->deferred = [];

        return true;
    }
}
