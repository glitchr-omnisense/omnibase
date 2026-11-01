<?php

namespace Base\Database\Repository\Finder;

/**
 * What the finder language asks of the entity a finder is called on, whatever
 * keeps it: Doctrine's metadata (ServiceEntityParser), Eloquent's tables from
 * Omnibridge\Schema (omnibridge/eloquent), or anything that knows its fields.
 */
interface Subject
{
    /** The entity's class, the one its mapping names (App\Entity\X where the application has one). */
    public function getClassName(): string;

    /**
     * The entity's fields, those a name must not mistake for a word of the
     * language (a field "postedOrDrafted" holds "Or").
     *
     * @return list<string>
     */
    public function getFieldNames(): array;

    /** Whether the entity has that field or association (a real one wins over a Query-by-Example clause). */
    public function hasField(string $name): bool;

    /**
     * A Query-by-Example's criteria (findByModel($select)): the set fields of the
     * object given, as field => value.
     *
     * @return array<string, mixed>
     */
    public function criteriaFromSelect(object $select): array;
}
