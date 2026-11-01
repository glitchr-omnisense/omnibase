<?php

namespace Base\Database\Backend;

/**
 * The cache a persistence keeps of entities between requests (plan "omnibase
 * agnostic", step F2d): what is evicted when a row is written behind it.
 * Doctrine's is its second-level cache, a persistence with none answers
 * nothing to evict; registered per unit of work in
 * Base\Database\Attribute\Backend\Backends.
 */
interface EntityCache
{
    /** Whether the persistence keeps such a cache (Doctrine: the second-level cache is enabled). */
    public function enabled(): bool;

    /** The entity of that class and identifier forgotten by the cache. */
    public function evict(string $class, mixed $id): void;

    /** Every entity of that class forgotten by the cache. */
    public function evictClass(string $class): void;
}
