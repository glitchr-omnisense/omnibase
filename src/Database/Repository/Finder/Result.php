<?php

namespace Base\Database\Repository\Finder;

use Base\Service\Model\Pages;

/**
 * What a finder that finds several rows hands back (findBy...(), findAll(), findLast...(), findAtMost...(),
 * cacheBy...()): the compiled query, run when its result is asked, as often as it is asked. Each persistence
 * answers it over its own query; nothing of it shows.
 *
 * It is also Pages (count() in the database, the query's limit and offset left out; slice() one page by the query's
 * order): what Pagination pages through.
 *
 * @extends \IteratorAggregate<int, mixed>
 */
interface Result extends Pages, \IteratorAggregate
{
    /** @return list<mixed> the entities, or rows holding the entity and the values selected beside it */
    public function getResult(): array;

    /** The one result, null without one; more than one is refused. */
    public function getOneOrNullResult(): mixed;

    /** The one value of the one row (a count, an aggregate); no row or more than one is refused. */
    public function getSingleScalarResult(): mixed;

    /** @return list<array<string, mixed>> the rows as arrays, no entity made */
    public function getArrayResult(): array;

    /** @return iterable<int, mixed> the results one by one */
    public function toIterable(): iterable;
}
