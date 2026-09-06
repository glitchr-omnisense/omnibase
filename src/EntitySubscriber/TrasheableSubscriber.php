<?php

namespace Base\EntitySubscriber;

use Base\Database\Attribute\Trasheable;
use Base\Entity\Extension\TrashBall;
use Base\Enum\EntityAction;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Throwable;

/**
 * Turns `$em->remove()` into a soft delete for every entity carrying
 * `#[Trasheable]` - Thread and, through it, articles, galleries, comments,
 * guestbook messages, calendars and newsletters.
 *
 * ## How the DELETE is cancelled
 *
 * preRemove cannot veto anything, so the interception happens at onFlush,
 * after computeChangeSets has scheduled everything but before any SQL runs:
 *
 *   - the deletion field is stamped,
 *   - `persist()` on an entity in STATE_REMOVED puts it back to STATE_MANAGED
 *     and drops it from the deletion queue - that is what cancels the DELETE,
 *   - scheduleExtraUpdate then emits the UPDATE that writes the stamp.
 *
 * ## Why the cascade has to be rescued
 *
 * `Thread::$translations` is mapped cascade: [persist, refresh, remove], so by
 * the time we get here Doctrine has already queued every ThreadIntl row for
 * deletion. Cancelling only the parent would trash an article and destroy its
 * title, headline, excerpt and content on the way in - restoring would hand
 * back an empty shell. So every entity the removal cascaded to is rescued with
 * it, recursively.
 *
 * ## Hard deletion
 *
 * An entity whose deletion field is ALREADY stamped is left alone and deleted
 * for real. That is deliberate and is the only escape hatch: emptying the
 * trash, and the "delete permanently" action, both work by removing an
 * already-trashed entity.
 *
 * ## Why the second-level cache has to be evicted
 *
 * Thread is `#[Cache(usage: "NONSTRICT_READ_WRITE")]`, and the L2 entity
 * region is keyed by id alone - Doctrine folds the filter hash into query and
 * collection keys, but not into that one. A copy cached before the trashing
 * therefore still says deletedAt = null, which broke both directions: find()
 * kept handing out entities the filter had hidden, and a later remove() read
 * that stale null and soft-deleted an already-trashed entity instead of
 * purging it. Evicting on the way out puts the next read back on the database,
 * where the filter applies.
 */
class TrasheableSubscriber
{
    /** @var array<int, array{0: string, 1: mixed}> class + id of what this flush trashed */
    protected array $evictions = [];

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected string $emptyTrash = "+7 days",
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $classMetadata = $entityManager->getClassMetadata(get_class($entity));

            $trasheable = Trasheable::resolve($classMetadata->getName());
            if (!$trasheable) {
                continue;
            }

            $field = $trasheable->deletedAt;
            if (!$classMetadata->hasField($field)) {
                continue;
            }

            $reflProperty = $classMetadata->getReflectionProperty($field);
            $oldValue = $reflProperty->getValue($entity);

            // Already in the trash: this remove() is the purge, let it through.
            if ($oldValue instanceof DateTimeInterface) {
                continue;
            }

            $deletedAt = new DateTime();
            $reflProperty->setValue($entity, $deletedAt);

            $entityManager->persist($entity);
            $unitOfWork->propertyChanged($entity, $field, $oldValue, $deletedAt);
            $unitOfWork->scheduleExtraUpdate($entity, [$field => [$oldValue, $deletedAt]]);

            if ($trasheable->cascade) {
                $this->rescueCascade($entityManager, $unitOfWork, $entity, [spl_object_id($entity) => true]);
            }

            $this->trash($entityManager, $unitOfWork, $entity, $classMetadata, $trasheable, $deletedAt);

            $this->evictions[] = [$classMetadata->rootEntityName, $entity->getId()];
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if (empty($this->evictions)) {
            return;
        }

        $evictions = $this->evictions;
        $this->evictions = [];

        $cache = $event->getObjectManager()->getCache();
        if (!$cache) {
            return;
        }

        foreach ($evictions as [$className, $id]) {
            if ($id !== null) {
                $cache->evictEntity($className, $id);
            }
        }
    }

    /**
     * Pull back every entity this removal cascaded to, so a restore gets the
     * whole object graph and not just its root row.
     *
     * @param array<int, bool> $seen guards the cycles a self-referencing
     *                               hierarchy like Thread::$children makes easy
     */
    protected function rescueCascade(EntityManagerInterface $entityManager, UnitOfWork $unitOfWork, object $entity, array $seen): void
    {
        $classMetadata = $entityManager->getClassMetadata(get_class($entity));

        foreach ($classMetadata->associationMappings as $fieldName => $mapping) {
            if (!$mapping->isCascadeRemove() && !$mapping->orphanRemoval) {
                continue;
            }

            $value = $classMetadata->getFieldValue($entity, $fieldName);
            if ($value === null) {
                continue;
            }

            $children = is_iterable($value) ? $value : [$value];
            foreach ($children as $child) {
                if (!is_object($child)) {
                    continue;
                }

                $id = spl_object_id($child);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;

                if ($unitOfWork->getEntityState($child, UnitOfWork::STATE_NEW) !== UnitOfWork::STATE_REMOVED) {
                    continue;
                }

                // Back to STATE_MANAGED, out of the deletion queue. Nothing
                // else is needed: the child has no pending change set, so it
                // is simply left as it stands in the database.
                $entityManager->persist($child);

                $this->rescueCascade($entityManager, $unitOfWork, $child, $seen);
            }
        }
    }

    /**
     * Record the trash entry that the trash listing and the purge read from.
     */
    protected function trash(
        EntityManagerInterface $entityManager,
        UnitOfWork $unitOfWork,
        object $entity,
        ClassMetadata $classMetadata,
        Trasheable $trasheable,
        DateTime $deletedAt,
    ): void {
        $trashBall = new TrashBall();
        $trashBall->setEntityClass($classMetadata->getName());
        $trashBall->setEntityId($entity);
        $trashBall->setAction(EntityAction::DELETE);
        $trashBall->setPermanentAfter(new DateTime($trasheable->expiry ?? $this->emptyTrash));

        // The label is snapshotted rather than resolved at display time: the
        // trash has to stay readable even for an entity whose __toString()
        // depends on translations or on a parent that has since gone.
        $label = null;
        try {
            $label = method_exists($entity, "__toString") ? trim((string) $entity) : null;
        } catch (Throwable) {
            $label = null;
        }

        $trashBall->setEntityData(array_filter([
            "label" => $label ?: null,
            "deletedAt" => $deletedAt->format(DATE_ATOM),
        ]));

        $entityManager->persist($trashBall);

        // AFTER persist: #[Timestamp(on: "create")]'s own prePersist would
        // overwrite it, with a DateTime it memoised on a cached attribute
        // instance - see the note in VersionableSubscriber. "Deleted on" has
        // to be the moment of the deletion.
        $trashBall->setCreatedAt($deletedAt);

        $unitOfWork->computeChangeSet($entityManager->getClassMetadata(TrashBall::class), $trashBall);
    }
}
