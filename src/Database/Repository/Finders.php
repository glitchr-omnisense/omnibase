<?php

namespace Base\Database\Repository;

use Base\Database\Mapping\EntityShapes;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The persistence installed, as a repository sees it: what ServiceEntityRepository is built from, in place of an
 * ORM's registry. One implementation per persistence (omnibridge/doctrine's, omnibridge/eloquent's), bound by the
 * host under this name; the repositories - Base\Repository\*, a bundle's, App\Repository\* - are the same classes
 * on each.
 */
interface Finders
{
    /**
     * The engine of an entity class: the finder language compiled and run.
     *
     * @param class-string $class
     */
    public function of(string $class): Finder;

    /** The entities' shapes: fields, associations, tables. */
    public function shapes(): EntityShapes;

    /** What the repositories write through. */
    public function unitOfWork(): UnitOfWork;

    /** The persistence's name: "doctrine", "eloquent". */
    public function backend(): string;

    /**
     * SQL on the persistence's connection, the repositories' common language underneath (findValuesBy(), updateBy(),
     * lockOneBy(), sql()): the rows of a SELECT (column => value), the rows changed otherwise. Positional `?`
     * parameters; `{selection:N}` stands for the identifiers of the rows of `$selections[N] = [class, criteria]`, as
     * the persistence's own finder selects them (its compiled criteria, scopes and discriminator included).
     *
     * @internal what ServiceEntityRepository builds on; an application calls its repository
     *
     * @param list<mixed>                                     $parameters
     * @param list<array{0: class-string, 1: array<int|string, mixed>}> $selections
     *
     * @return list<array<string, mixed>>|int
     */
    public function sql(string $sql, array $parameters = [], array $selections = []): array|int;

    /** The database the connection speaks to: "mysql", "sqlite", "pgsql". @internal */
    public function platform(): string;
}
