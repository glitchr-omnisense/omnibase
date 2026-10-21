<?php

namespace Base\Repository;

use Base\Database\Repository\Entities;
use Base\Entity\Thread;

/**
 * What a site asks of its threads (a destination, an article, a gallery, a
 * calendar...): the one of a slug, of a parent, of a state, the ones under a
 * parent, the previous and the next by publication. The interface the code
 * of an application and of the core is given (audit "lesringostars face au
 * master plan", step R15; see Base\Database\Repository\Entities); Doctrine's
 * ThreadRepository answers it, and every site repository extending it.
 *
 * Nothing of the ORM in or out: $states is a state of Base\Enum\ThreadState
 * or a list of them (an empty list: any state), $orderBy field => ASC|DESC,
 * a thread or null, a list of threads, an integer. ThreadRepository answers
 * each through its finder (cacheOneBySlug()...), the queries it ran before:
 * the same SQL, its results cached the same way.
 *
 * @template T of Thread
 *
 * @extends Entities<T>
 */
interface Threads extends Entities
{
    /** @return T|null */
    public function oneBySlug(string $slug): ?Thread;

    /**
     * The first in that order of these states, of that slug when one is given
     * (no slug: of any) - a page's thread from its address, which may name none.
     *
     * @param array<string, string> $orderBy
     *
     * @return T|null
     */
    public function oneBySlugAndState(?string $slug, array|string $states, array $orderBy = []): ?Thread;

    /** @return T|null */
    public function oneByState(array|string $states, array $orderBy = []): ?Thread;

    /** @return T|null the first of all in that order */
    public function first(array $orderBy): ?Thread;

    /** @return list<T> */
    public function byState(array|string $states, array $orderBy = []): array;

    /**
     * Every one in one of these states, in no order, read afresh (not from the
     * result cache): what a crawler may list.
     *
     * @param list<string> $states
     *
     * @return list<T>
     */
    public function byStates(array $states): array;

    /** @return T|null */
    public function oneByParentAndSlugAndState(?Thread $parent, string $slug, array|string $states, array $orderBy = []): ?Thread;

    /** @return list<T> */
    public function byParentAndState(?Thread $parent, array|string $states, array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    /**
     * Those published within $within of now (a relative date: "+8 days").
     *
     * @return list<T>
     */
    public function byParentAndStatePublishedWithin(?Thread $parent, array|string $states, string $within, array $orderBy = []): array;

    /** @return T|null */
    public function oneByStateAndSlugStartingWith(array|string $states, string $prefix): ?Thread;

    /** How many were published less than $age ago (seconds, or a relative date). */
    public function countByStatePublishedYoungerThan(array|string $states, int|string $age): int;

    /**
     * The one before $at by its field (['publishedAt' => $date]), of the class
     * $class exactly when one is given.
     *
     * @param array<string, mixed> $at
     *
     * @return T|null
     */
    public function previousOne(array $at, array|string $states, ?string $class = null): ?Thread;

    /**
     * @param array<string, mixed> $at
     *
     * @return T|null
     */
    public function nextOne(array $at, array|string $states, ?string $class = null): ?Thread;

    /**
     * The level of the class's #[Hierarchify] (the last one by default).
     *
     * @return string|null
     */
    public function getHierarchy($id = -1, ?string $separator = null);
}
