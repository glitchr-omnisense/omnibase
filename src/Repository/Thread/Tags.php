<?php

namespace Base\Repository\Thread;

use Base\Database\Repository\Entities;
use Base\Entity\Thread\Tag;

/**
 * A site's tags by their slugs (audit "lesringostars face au master plan",
 * step R15; see Base\Database\Repository\Entities): TagRepository answers it
 * through its finder, with the queries its callers ran by name before.
 *
 * @template T of Tag
 *
 * @extends Entities<T>
 */
interface Tags extends Entities
{
    /** @return T|null */
    public function oneBySlug(string $slug): ?Tag;

    /**
     * @param list<string> $slugs
     *
     * @return list<T> in no particular order
     */
    public function bySlugs(array $slugs): array;
}
