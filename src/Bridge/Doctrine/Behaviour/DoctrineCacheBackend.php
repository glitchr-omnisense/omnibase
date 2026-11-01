<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Attributes\AbstractAttribute;
use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\CacheBackend;
use Base\Database\Attribute\Cache;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * #[Cache]'s part on Doctrine, its second-level cache (plan "omnibase agnostic",
 * step F2b; moves to omnibridge/doctrine at F3). The regions themselves are
 * declared by Cache::loadClassMetadata(), a mapping hook of Doctrine's.
 */
final class DoctrineCacheBackend implements CacheBackend
{
    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function evict(Cache $cache, object $entity, ?string $property = null): void
    {
        $entityManager = $this->unitOfWork->entityManager();
        $classMetadata = $entityManager->getClassMetadata($entity::class);

        if ($property) {
            $secondLevelCache = $entityManager->getCache();
            if (!$secondLevelCache) {
                return;
            }

            $manipulator = AbstractAttribute::getClassMetadataManipulator();
            if (!$manipulator->hasAssociation($entity, $property)) {
                return;
            }

            if ($manipulator->isToManySide($entity, $property)) {
                $secondLevelCache->evictCollection($classMetadata->getName(), $property, $entity->getId());

                return;
            }

            $secondLevelCache->evictEntity($classMetadata->getName(), $entity->getId());

            return;
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($classMetadata->associationMappings as $association => $associationMapping) {
            $isTargetEntityCached = !empty(AbstractAttribute::getAttributeReader()->getClassAttributes($associationMapping['targetEntity'], Cache::class));
            if (!$isTargetEntityCached) {
                continue;
            }

            $propertyAccessor->getValue($entity, $association); // readout value
            $this->evict($cache, $entity, $association);
        }
    }
}
