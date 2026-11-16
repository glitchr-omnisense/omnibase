<?php

namespace Base\Database\Repository;

/**
 * The engine of one entity class on the persistence installed (Finders::of()): what ServiceEntityRepository
 * delegates to. It reads a finder's name and arguments with the core's Finder\Parser, compiles the Finder\Query
 * into its own query and runs it. Internal to the repositories: an application calls its repository.
 */
interface Finder
{
    /** The entity's class, as its mapping names it. */
    public function className(): string;

    /**
     * A call of the finder language (findBySlug, findOneBy, count, cacheOneByStateAndSlugStartingWith...): an entity,
     * null, an int, a value, or a Finder\Result for the finders of several rows.
     *
     * @param list<mixed> $arguments
     */
    public function run(string $method, array $arguments): mixed;

    /** The entity of this identifier (the identity map first). */
    public function find(mixed $id): ?object;

    /** Forgets the records loaded so far. */
    public function clear(): void;
}
