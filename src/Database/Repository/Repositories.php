<?php

namespace Base\Database\Repository;

/**
 * The entities of any class, by its name (audit "lesringostars face au master
 * plan", step R28): what code that is handed a class at run time - a form
 * field's choices, a command over every uploading entity, the search's sort
 * by hierarchy - asks, where it called the entity manager's getRepository()
 * before. Nothing of the ORM in or out.
 *
 * Base\Bridge\Doctrine\DoctrineRepositories answers it with the entity
 * manager's repositories: omnibase's (ServiceEntityRepository, every
 * Base\Repository\ and App\Repository\ class) as they are, Doctrine's plain
 * EntityRepository behind an Entities adapter. Another persistence answers
 * the same.
 */
interface Repositories
{
    /**
     * @param class-string $class
     *
     * @return Entities<object>
     */
    public function of(string $class): Entities;

    /**
     * The entities of these ids, through omnibase's finder cache where the
     * repository is omnibase's (cacheById), else by its identifier.
     *
     * @param class-string     $class
     * @param list<int|string> $ids
     *
     * @return list<object>
     */
    public function byIds(string $class, array $ids): array;

    /**
     * The entity of this id, through omnibase's finder cache where the
     * repository is omnibase's (cacheOneById), else find().
     *
     * @param class-string $class
     */
    public function oneById(string $class, mixed $id): ?object;
}
