<?php

namespace Base\Service;

use Base\Database\Attribute\Trasheable;
use Base\Entity\Extension\Abstract\AbstractExtension;
use Base\Entity\Extension\TrashBall;
use Base\Repository\Extension\TrashBallRepository;
use Closure;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
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

    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
    }

    protected function repository(): TrashBallRepository
    {
        return $this->entityManager->getRepository(TrashBall::class);
    }

    /**
     * Run something with trashed entities visible.
     */
    public function withTrash(Closure $callable): mixed
    {
        $filters = $this->entityManager->getFilters();
        $wasEnabled = $filters->isEnabled(self::FILTER);

        if ($wasEnabled) {
            $filters->disable(self::FILTER);
        }

        try {
            return $callable();
        } finally {
            if ($wasEnabled && !$filters->isEnabled(self::FILTER)) {
                $filters->enable(self::FILTER);
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
                return $this->entityManager->find($entityClass, $entityId);
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
            $this->entityManager->remove($trashBall);
            $this->entityManager->flush();

            return false;
        }

        $trasheable = Trasheable::resolve($entity::class);
        $classMetadata = $this->entityManager->getClassMetadata($entity::class);
        $classMetadata->getReflectionProperty($trasheable->deletedAt)->setValue($entity, null);

        $this->entityManager->remove($trashBall);
        $this->entityManager->flush();

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
            $this->entityManager->remove($entity);
        }

        $this->entityManager->remove($trashBall);
        $this->entityManager->flush();

        // Revisions and any other extension rows kept for this entity: they
        // describe something that no longer exists.
        if ($entityClass && $entityId) {
            $this->entityManager->createQuery(
                "DELETE FROM " . AbstractExtension::class . " e WHERE e.entityClass = :class AND e.entityId = :id"
            )->setParameters(["class" => $entityClass, "id" => $entityId])->execute();
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
        $cache = $this->entityManager->getCache();
        if (!$cache) {
            return;
        }

        $classMetadata = $this->entityManager->getClassMetadata($entity::class);
        $id = $classMetadata->getIdentifierValues($entity);

        if (count($id) === 1) {
            $cache->evictEntity($classMetadata->rootEntityName, reset($id));
        }
    }
}
