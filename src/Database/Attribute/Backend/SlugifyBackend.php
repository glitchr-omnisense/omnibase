<?php

namespace Base\Database\Attribute\Backend;

/**
 * #[Slugify]'s part of the persistence (plan "omnibase agnostic", step F2b):
 * what the running flush is about to write, so that two new entities of the
 * same flush do not get the same slug, and whether the database still holds a
 * unique index on the slug's column alone (per-type slugs wait for its
 * migration).
 */
interface SlugifyBackend
{
    /**
     * The entities the running flush inserts, then those it updates, in the
     * order it writes them.
     *
     * @return list<object>
     */
    public function pendingWrites(): array;

    /** Whether the table of that class holds a unique index (not the primary key) on the property's column alone. */
    public function uniqueAlone(string $class, string $property): bool;
}
