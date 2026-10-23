<?php

namespace Base\Database\Repository;

/**
 * The entities of one class, whatever keeps them: what an application asks
 * every repository of omnibase, with nothing of the ORM in what it takes or
 * gives back - arrays of criteria and of order (field => ASC|DESC), the
 * entities themselves, lists of them, integers.
 *
 * The persistence half of Marco's bridge principle (audit "lesringostars face
 * au master plan", step R15): the structure is described outside Doctrine
 * (R14, Omnibridge\Schema), and the code of an application and of the core
 * reaches its repositories through interfaces - this one and those of each
 * entity, Base\Repository\Threads, Users... and an application's own
 * (App\Repository\Article\Articles...) -, Doctrine's repository behind them.
 * Another persistence (Eloquent, or anything better suited to a host)
 * implements the same interfaces over the same tables.
 *
 * ServiceEntityRepository implements it with Doctrine's methods of the same
 * names: find(), findAll(), findBy(), findOneBy(), count(); persist(),
 * remove() and flush() its unit of work.
 *
 * @template T of object
 */
interface Entities
{
    /**
     * @param int|string $id
     *
     * @return T|null
     */
    public function find($id): ?object;

    /** @return list<T> */
    public function findAll(): array;

    /**
     * @param array<string, mixed>       $criteria field => value, a list of values for one of them
     * @param array<string, string>|null $orderBy  field => ASC|DESC
     * @param int|null                   $limit
     * @param int|null                   $offset
     *
     * @return list<T>
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array;

    /**
     * @param array<string, mixed>       $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return T|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    /** @param array<string, mixed> $criteria */
    public function count(array $criteria = []): int;

    /** @param T $entity kept at the next flush, at once with $flush */
    public function persist($entity, bool $flush = false): void;

    /** @param T $entity */
    public function remove($entity, bool $flush = false): void;

    /**
     * Writes what was persisted, changed or removed.
     *
     * @return void
     */
    public function flush(bool $autoclear = false);
}
