<?php

namespace Base\Service;

use Base\Database\Attribute\Backend\Backends;
use Base\Database\Attribute\Trasheable;
use Base\Database\Backend\BulkDeletes;
use Base\Database\Backend\EntityCache;
use Base\Database\Scope\TrashScope;
use Base\Database\UnitOfWorks;
use Base\Entity\Extension\Abstract\AbstractExtension;
use Base\Entity\Extension\TrashBall;
use Base\Repository\Extension\TrashBalls;
use Closure;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Persistence\Backend;
use Omnibridge\Persistence\UnitOfWork;
use Throwable;

/**
 * Reads and empties what TrasheableSubscriber put aside.
 *
 * Every method here works with the trash filter turned off, because the whole
 * point of the filter is that trashed entities are unreachable through the
 * ORM - including to the code meant to bring them back. withTrash() is the
 * only place that is allowed, and it restores the previous filter state even
 * when the callable throws, so an exception mid-restore cannot leave the rest
 * of the request able to see deleted content.
 */
class TrashManager
{
    public const FILTER = "trash_filter";

    protected readonly UnitOfWork $unitOfWork;

    /** @param UnitOfWork|EntityManagerInterface $unitOfWork the entity manager is deprecated (until 6.0): adapted by UnitOfWorks */
    public function __construct(
        UnitOfWork|EntityManagerInterface $unitOfWork,
        protected TrashBalls $trashBalls,
    ) {
        $this->unitOfWork = UnitOfWorks::of($unitOfWork);
    }

    /** The trash by its interface (step R28), where it asked the entity manager's repository. */
    protected function repository(): TrashBalls
    {
        return $this->trashBalls;
    }

    /**
     * The entries past their grace period at $now (now when null), the oldest first: what purge() destroys.
     *
     * @return TrashBall[]
     */
    public function expired(?DateTimeInterface $now = null, ?int $limit = null): array
    {
        return $this->repository()->expired($now, $limit);
    }

    /**
     * Run something with trashed entities visible.
     */
    public function withTrash(Closure $callable): mixed
    {
        // The trash scope (step F2e: Doctrine's filter "trash_filter" behind the persistence's Backend).
        $backend = Backends::of($this->unitOfWork, Backend::class);
        $wasEnabled = $backend->isScopeEnabled(TrashScope::NAME);

        if ($wasEnabled) {
            $backend->disableScope(TrashScope::NAME);
        }

        try {
            return $callable();
        } finally {
            if ($wasEnabled && !$backend->isScopeEnabled(TrashScope::NAME)) {
                $backend->enableScope(TrashScope::NAME);
            }
        }
    }

    /**
     * @return TrashBall[]
     */
    public function contents(?string $entityClass = null, ?int $limit = null, int $offset = 0): array
    {
        return $this->repository()->contents($entityClass, $limit, $offset);
    }

    public function count(?string $entityClass = null): int
    {
        return $this->repository()->countContents($entityClass);
    }

    /**
     * The entity a trash entry stands for, or null if it is no longer there -
     * a trash entry outliving its entity is possible (a hard delete elsewhere,
     * a cascade from a parent) and must not be an error.
     */
    public function entity(TrashBall $trashBall): ?object
    {
        $entityClass = $trashBall->getEntityClass();
        $entityId = $trashBall->getEntityId();

        if (!$entityClass || !$entityId || !class_exists($entityClass)) {
            return null;
        }

        return $this->withTrash(function () use ($entityClass, $entityId) {
            try {
                return UnitOfWorks::repositories($this->unitOfWork)->of($entityClass)->find($entityId);
            } catch (Throwable) {
                return null;
            }
        });
    }

    /**
     * Put an entity back. The trash entry goes with it - being in the trash
     * and having a trash entry are the same fact, and leaving one behind would
     * show a restored article as still deleted.
     */
    public function restore(TrashBall $trashBall): bool
    {
        $entity = $this->entity($trashBall);

        if ($entity === null || !Trasheable::has($entity::class)) {
            // Nothing to restore: drop the dangling entry rather than leave the
            // trash listing pointing at something that cannot be brought back.
            $this->unitOfWork->remove($trashBall);
            $this->unitOfWork->flush();

            return false;
        }

        $trasheable = Trasheable::resolve($entity::class);
        self::property($entity, $trasheable->deletedAt)->setValue($entity, null);

        $this->unitOfWork->remove($trashBall);
        $this->unitOfWork->flush();

        $this->evict($entity);

        return true;
    }

    /**
     * Destroy an entity for good, along with everything recorded about it.
     *
     * The removal only goes through because the deletion field is already
     * stamped - that is the escape hatch TrasheableSubscriber leaves open, and
     * the reason this cannot be used to bypass the trash on a live entity.
     */
    public function destroy(TrashBall $trashBall): bool
    {
        $entityClass = $trashBall->getEntityClass();
        $entityId = $trashBall->getEntityId();
        $entity = $this->entity($trashBall);

        if ($entity !== null) {
            $this->unitOfWork->remove($entity);
        }

        $this->unitOfWork->remove($trashBall);
        $this->unitOfWork->flush();

        // Revisions and any other extension rows kept for this entity: they
        // describe something that no longer exists.
        if ($entityClass && $entityId) {
            Backends::of($this->unitOfWork, BulkDeletes::class)->deleteWhere(AbstractExtension::class, ["entityClass" => $entityClass, "entityId" => $entityId]);
        }

        return $entity !== null;
    }

    /**
     * Destroy everything whose grace period has run out.
     *
     * @return int how many entities were actually destroyed
     */
    public function purge(?DateTimeInterface $now = null, ?int $limit = null): int
    {
        $destroyed = 0;
        foreach ($this->repository()->expired($now, $limit) as $trashBall) {
            $destroyed += $this->destroy($trashBall) ? 1 : 0;
        }

        return $destroyed;
    }

    protected function evict(object $entity): void
    {
        $cache = Backends::of($this->unitOfWork, EntityCache::class);
        if (!$cache->enabled()) {
            return;
        }

        $id = $this->unitOfWork->identifier($entity);
        if (null !== $id && !is_array($id)) {
            $cache->evict(UnitOfWorks::shapes($this->unitOfWork)->shape($entity)->rootClass(), $id);
        }
    }

    /** The entity's property where its class or a parent declares it (Doctrine's reflection property before, step F2e). */
    private static function property(object $entity, string $name): \ReflectionProperty
    {
        for ($class = new \ReflectionClass($entity); false !== $class; $class = $class->getParentClass()) {
            if ($class->hasProperty($name) && $class->getProperty($name)->getDeclaringClass()->getName() === $class->getName()) {
                return $class->getProperty($name);
            }
        }

        throw new \LogicException(sprintf('%s has no property %s.', $entity::class, $name));
    }
}
