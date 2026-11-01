<?php

namespace Base\Database\Attribute\Backend;

/**
 * #[OrderColumn]'s part of the persistence (plan "omnibase agnostic", step F2c): a to-many association
 * the persistence loads lazily keeps its stored positions until it is first read, instead of being
 * loaded at every entity loaded to be sorted. Doctrine: the PersistentCollection's inner collection
 * swapped for an OrderedArrayCollection. A persistence without lazy collections answers false, and the
 * association is sorted at once.
 */
interface OrderColumnBackend
{
    /**
     * Whether the persistence took the ordering of that collection over (it sorts itself when loaded).
     *
     * @param list<mixed> $positions the stored order (identifiers or keys)
     */
    public function orderLazily(object $entity, mixed $collection, array $positions): bool;
}
