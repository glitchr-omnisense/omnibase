<?php

namespace Base\Database\Backend;

/**
 * Rows deleted by criteria in one statement, their entities never read (plan
 * "omnibase agnostic", step F2e): what is recorded about an entity destroyed
 * for good (its revisions, its trash entry's siblings). Doctrine's is a DQL
 * DELETE; registered per unit of work in Base\Database\Attribute\Backend\Backends.
 */
interface BulkDeletes
{
    /**
     * @param class-string         $class    the entity class (its subclasses' rows too, as the persistence maps them)
     * @param array<string, mixed> $criteria field => value, combined with And
     *
     * @return int the rows deleted
     */
    public function deleteWhere(string $class, array $criteria): int;
}
