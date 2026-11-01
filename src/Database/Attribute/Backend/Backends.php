<?php

namespace Base\Database\Attribute\Backend;

use Base\Exception\UnsupportedBehaviourException;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The persistence's part of each behaviour, by unit of work (plan "omnibase
 * agnostic", step F2b).
 *
 * A behaviour attribute answers omnisense/omnibridge's neutral lifecycle hooks
 * with the entity and its unit of work. What only a given persistence can do
 * (Doctrine's forced update, its scheduled writes, its schema, its second-level
 * cache) is asked of a small interface of this namespace - VaultBackend,
 * SlugifyBackend, CacheBackend -, implemented once per persistence and
 * registered here for that persistence's UnitOfWork class. Doctrine's are in
 * Base\Bridge\Doctrine\Behaviour (they move to omnibridge/doctrine at step F3,
 * an Eloquent version comes with omnibridge/eloquent at F7).
 *
 * One instance of a backend per unit of work, made on first use from the unit
 * of work itself (the implementation's constructor takes it), so that two
 * entity managers never share one.
 */
final class Backends
{
    /** @var array<class-string<UnitOfWork>, array<class-string, class-string|\Closure(UnitOfWork): object>> */
    private static array $factories = [];

    /** @var \WeakMap<UnitOfWork, array<class-string, object>>|null */
    private static ?\WeakMap $instances = null;

    /**
     * @param class-string<UnitOfWork>                       $unitOfWork the persistence's UnitOfWork class (a subclass matches too)
     * @param class-string                                   $behaviour  the backend interface (VaultBackend::class...)
     * @param class-string|\Closure(UnitOfWork): object      $backend    its implementation, built with the unit of work
     */
    public static function register(string $unitOfWork, string $behaviour, string|\Closure $backend): void
    {
        self::$factories[$unitOfWork][$behaviour] = $backend;
        self::$instances = null;
    }

    public static function has(UnitOfWork $unitOfWork, string $behaviour): bool
    {
        return null !== self::factory($unitOfWork, $behaviour);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $behaviour
     *
     * @return T
     *
     * @throws UnsupportedBehaviourException when the persistence registered none
     */
    public static function of(UnitOfWork $unitOfWork, string $behaviour): object
    {
        self::$instances ??= new \WeakMap();
        if (isset(self::$instances[$unitOfWork][$behaviour])) {
            return self::$instances[$unitOfWork][$behaviour];
        }

        $factory = self::factory($unitOfWork, $behaviour) ?? throw UnsupportedBehaviourException::for($behaviour, $unitOfWork);
        $backend = $factory instanceof \Closure ? $factory($unitOfWork) : new $factory($unitOfWork);
        if (!$backend instanceof $behaviour) {
            throw new \LogicException(sprintf('The %s registered for %s is a %s.', $behaviour, $unitOfWork::class, get_debug_type($backend)));
        }

        $instances = self::$instances[$unitOfWork] ?? [];
        $instances[$behaviour] = $backend;
        self::$instances[$unitOfWork] = $instances;

        return $backend;
    }

    /** @return class-string|\Closure|null */
    private static function factory(UnitOfWork $unitOfWork, string $behaviour): string|\Closure|null
    {
        foreach (self::$factories as $class => $backends) {
            if ($unitOfWork instanceof $class && isset($backends[$behaviour])) {
                return $backends[$behaviour];
            }
        }

        return null;
    }
}
