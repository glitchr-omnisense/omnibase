<?php

namespace Base\DatabaseSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\OwningSideMapping;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;

/**
 * Keeps the second-level cache's INVERSE collections true.
 *
 * Doctrine refreshes the cached collection of the side that owns an
 * association and leaves the other side's cached collection as it was: a
 * product that drops a feature still shows in Feature::$products (the
 * tag's threads) until the cache expires, so a "still in use" check or a
 * listing reads a relation the database no longer has. On each flush, the
 * inverse collection of every entity that joined or left an owning side -
 * a many-to-many collection, or a many-to-one/one-to-one reference - is
 * evicted, and reloads from the database on next read.
 *
 * The whole region of that association goes, not the one entry: Doctrine's
 * own Cache::evictCollection() builds its key without the SQL-filter hash
 * its persisters add, so with a filter enabled it evicts nothing, and each
 * set of enabled filters (front, back office) caches its own copy anyway.
 */
class InverseCollectionCacheSubscriber
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $cache = $entityManager->getCache();
        if (null === $cache) {
            return;
        }

        $unitOfWork = $entityManager->getUnitOfWork();
        $regions = [];
        $evict = function (string $targetClass, string $inversedBy, ?object $related) use (&$regions): void {
            if (null !== $related) {
                $regions[$targetClass . '::' . $inversedBy] = [$targetClass, $inversedBy];
            }
        };

        // many-to-many owning sides, changed or replaced: the region goes
        // whether or not the members are known (a collection a form swapped
        // for a new one was often never loaded, its snapshot is empty)
        foreach (array_merge($unitOfWork->getScheduledCollectionUpdates(), $unitOfWork->getScheduledCollectionDeletions()) as $collection) {
            $mapping = $collection->getMapping();
            if ($mapping instanceof OwningSideMapping && null !== $mapping->inversedBy) {
                $regions[$mapping->targetEntity . '::' . $mapping->inversedBy] = [$mapping->targetEntity, $mapping->inversedBy];
            }
        }

        // to-one owning sides: the entity pointed to before and after
        $entities = [
            'insert' => $unitOfWork->getScheduledEntityInsertions(),
            'update' => $unitOfWork->getScheduledEntityUpdates(),
            'delete' => $unitOfWork->getScheduledEntityDeletions(),
        ];
        foreach ($entities as $operation => $scheduled) {
            foreach ($scheduled as $entity) {
                $metadata = $entityManager->getClassMetadata($entity::class);
                $changeSet = 'update' === $operation ? $unitOfWork->getEntityChangeSet($entity) : [];
                foreach ($metadata->associationMappings as $field => $mapping) {
                    if (!$mapping instanceof ToOneOwningSideMapping || null === $mapping->inversedBy) {
                        continue;
                    }
                    if ('update' === $operation) {
                        if (!\array_key_exists($field, $changeSet)) {
                            continue;
                        }
                        [$before, $after] = $changeSet[$field];
                        $evict($mapping->targetEntity, $mapping->inversedBy, $before);
                        $evict($mapping->targetEntity, $mapping->inversedBy, $after);
                    } else {
                        $evict($mapping->targetEntity, $mapping->inversedBy, $metadata->getFieldValue($entity, $field));
                    }
                }
            }
        }

        foreach ($regions as [$targetClass, $inversedBy]) {
            if ($this->isCached($entityManager, $targetClass, $inversedBy)) {
                $cache->evictCollectionRegion($targetClass, $inversedBy);
            }
        }
    }

    private function isCached(EntityManagerInterface $entityManager, string $class, string $association): bool
    {
        $metadata = $entityManager->getClassMetadata($class);

        return $metadata->hasAssociation($association) && null !== ($metadata->associationMappings[$association]->cache ?? null);
    }
}
