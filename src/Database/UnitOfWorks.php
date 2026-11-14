<?php

namespace Base\Database;

use Base\Database\Attribute\Backend\Backends;
use Base\Database\Mapping\EntityShapes;
use Base\Database\Repository\Repositories;
use Omnibridge\Persistence\UnitOfWork;

/**
 * omnisense/omnibridge's UnitOfWork for a persistence's own manager (plan
 * "omnibase agnostic", step F2d): the unit of work itself, or Doctrine's entity
 * manager adapted once - what a host or a test hands to the core's services.
 *
 * And, from a unit of work, the parts of its persistence registered in
 * Backends: its repositories by class (Repositories), its entities' shapes
 * (EntityShapes), and what only that persistence does (Base\Database\Backend\*).
 * A service of the core takes a UnitOfWork and asks nothing else of the ORM; since
 * 6.0 none accepts the entity manager in its place: of() is what makes one of it.
 */
final class UnitOfWorks
{
    /**
     * The persistences' managers a unit of work is made from, by name, so that
     * nothing here loads them: omnibridge/doctrine's adapter (in the core until
     * step F3).
     *
     * @var array<class-string, class-string<UnitOfWork>>
     */
    public const ADAPTERS = [
        'Doctrine\ORM\EntityManagerInterface' => 'Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork',
    ];

    /** @var array<class-string, class-string<UnitOfWork>|\Closure(object): UnitOfWork> */
    private static array $adapters = [];

    /**
     * @param class-string                                         $manager the persistence's manager class (an interface matches its implementations)
     * @param class-string<UnitOfWork>|\Closure(object): UnitOfWork $adapter
     */
    public static function register(string $manager, string|\Closure $adapter): void
    {
        self::$adapters[$manager] = $adapter;
    }

    /** The unit of work itself, or one made from a persistence's manager. */
    public static function of(object $persistence): UnitOfWork
    {
        if ($persistence instanceof UnitOfWork) {
            return $persistence;
        }

        foreach (self::$adapters + self::ADAPTERS as $manager => $adapter) {
            if ($persistence instanceof $manager) {
                return $adapter instanceof \Closure ? $adapter($persistence) : new $adapter($persistence);
            }
        }

        throw new \InvalidArgumentException(sprintf('%s is neither a %s nor a persistence manager one is made from (%s).', get_debug_type($persistence), UnitOfWork::class, implode(', ', array_keys(self::$adapters + self::ADAPTERS))));
    }

    /** The repositories of the unit of work's persistence, by entity class. */
    public static function repositories(UnitOfWork $unitOfWork): Repositories
    {
        return Backends::of($unitOfWork, Repositories::class);
    }

    /** The entities' shapes as the unit of work's persistence maps them. */
    public static function shapes(UnitOfWork $unitOfWork): EntityShapes
    {
        return Backends::of($unitOfWork, EntityShapes::class);
    }
}
