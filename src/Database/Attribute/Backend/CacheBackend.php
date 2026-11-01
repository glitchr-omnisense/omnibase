<?php

namespace Base\Database\Attribute\Backend;

use Base\Database\Attribute\Cache;

/**
 * #[Cache]'s part of the persistence (plan "omnibase agnostic", step F2b): the
 * cache that keeps entities and their associations between requests (Doctrine's
 * second-level cache). A persistence without such a cache implements it as
 * nothing to do.
 */
interface CacheBackend
{
    /**
     * What the cache keeps of the entity dropped, so the next read goes to the
     * database: with a property, that association of the entity (its
     * collection, or the entity for a to-one side); without, every
     * association of it whose target is cached too.
     */
    public function evict(Cache $cache, object $entity, ?string $property = null): void;
}
