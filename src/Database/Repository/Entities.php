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
 * (App\Repository\Article\Articles...) -, the persistence installed behind them (Doctrine,
 * Eloquent, or anything better suited to a host), over the same tables.
 *
 * ServiceEntityRepository implements it on the persistence installed (Finders):
 * find(), findAll(), findBy(), findOneBy(), count() through its finder; persist(),
 * remove() and flush() through its unit of work.
 *
 * @template-covariant T of object
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
     * @param array<int|string, mixed>   $criteria field => value (null: IS NULL; a list: one of them, none for []),
     *                                             "association.field" => value (one association deep), Finder\Key,
     *                                             and Finder\Group entries under integer keys
     * @param array<string, string|list<mixed>>|null $orderBy field or "association.field" => ASC|DESC, in order; a list of
     *                                                       values orders by them (FIELD()); id => "rand" for a random order
     * @param int|null                   $limit
     * @param int|null                   $offset
     *
     * @return list<T>
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array;

    /**
     * @param array<int|string, mixed>                $criteria as findBy()'s
     * @param array<string, string|list<mixed>>|null $orderBy  as findBy()'s
     *
     * @return T|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    /** @param array<int|string, mixed> $criteria as findBy()'s, counted by the database */
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

    /** Forgets the records loaded so far: the next reads make new objects. */
    public function clear(): void;
}
