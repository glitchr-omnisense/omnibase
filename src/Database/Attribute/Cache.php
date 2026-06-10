<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAnnotation;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Base\Database\Entity\EntityExtension;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs as BaseLifecycleEventArgs;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Caching to an entity or a collection.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS", "PROPERTY"})
 */

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class Cache extends AbstractAnnotation implements ExtensionOptionInterface
{
    /**
     * @var string The concurrency strategy.
     * @Enum({"READ_ONLY", "NONSTRICT_READ_WRITE", "READ_WRITE"})
     */
    public string $usage = self::READ_ONLY;
    
    public const READ_ONLY = "READ_ONLY";
    public const NONSTRICT_READ_WRITE = "NONSTRICT_READ_WRITE";
    public const READ_WRITE = "READ_WRITE";

    /** @var string|null Cache region name. */
    public ?string $region;

    /**
     * @var int The concurrency strategy.
     */
    public int $associations = 0;

    public const ONE_TO_ONE = "ONE_TO_ONE";
    public const MANY_TO_ONE = "MANY_TO_ONE";
    public const ONE_TO_MANY = "ONE_TO_MANY";
    public const MANY_TO_MANY = "ONE_TO_ONE";

    public const TO_ONE = "TO_ONE";
    public const TO_MANY = "TO_MANY";
    public const ALL = "ALL";
    public const NONE = "NONE";

    public function __construct(string $usage = 'READ_ONLY', ?string $region = null, ?string $associations = null)
    {
        $this->usage = $usage;
        $this->region = $region;

        switch ($associations) {
            case self::ONE_TO_ONE:
                $this->associations = ClassMetadata::ONE_TO_ONE;
                break;
            case self::MANY_TO_ONE:
                $this->associations = ClassMetadata::MANY_TO_ONE;
                break;
            case self::ONE_TO_MANY:
                $this->associations = ClassMetadata::ONE_TO_MANY;
                break;
            case self::MANY_TO_MANY:
                $this->associations = ClassMetadata::MANY_TO_MANY;
                break;
            case self::TO_MANY:
                $this->associations = ClassMetadata::ONE_TO_MANY + ClassMetadata::MANY_TO_MANY;
                break;
            case self::TO_ONE:
                $this->associations = ClassMetadata::ONE_TO_ONE + ClassMetadata::MANY_TO_ONE;
                break;
            case self::ALL:
                $this->associations = ClassMetadata::ONE_TO_ONE + ClassMetadata::MANY_TO_ONE + ClassMetadata::ONE_TO_MANY + ClassMetadata::MANY_TO_MANY;
                break;

            default:
            case self::NONE:
                $this->associations = 0;
        }
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == EntityExtension::TARGET_CLASS || $object == EntityExtension::TARGET_PROPERTY);
    }

    /**
     * @param ClassMetadata $classMetadata
     * @return string
     */
    public function getRegion(ClassMetadata $classMetadata)
    {
        return $this->region ?? $this->getEntityManager()->getConfiguration()->getNamingStrategy()->classToTableName($classMetadata->rootEntityName);
    }

    /**
     * @param ClassMetadata $classMetadata
     * @param string $property
     * @return string
     */
    public function getRegionProperty(ClassMetadata $classMetadata, string $property)
    {
        return $this->getRegion($classMetadata) . "__" . $property;
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        $region = $this->getRegion($classMetadata);

        $usage = null;
        switch ($this->usage) {
            case self::READ_ONLY:
                $usage = ClassMetadata::CACHE_USAGE_READ_ONLY;
                break;
            case self::READ_WRITE:
                $usage = ClassMetadata::CACHE_USAGE_READ_WRITE;
                break;
            case self::NONSTRICT_READ_WRITE:
                $usage = ClassMetadata::CACHE_USAGE_NONSTRICT_READ_WRITE;
                break;
        }

        switch ($target) {

            case EntityExtension::TARGET_CLASS:

                $classMetadata->cache = [
                    "usage" => $usage,
                    "region" => $region,
                ];

                foreach ($classMetadata->associationMappings as $property => $associationMapping) {
                    $isTargetEntityCached = !empty($this->getAnnotationReader()->getClassAnnotations($associationMapping["targetEntity"], self::class));
                    if (!$isTargetEntityCached) {
                        continue;
                    }

                    $this->loadClassMetadata($classMetadata, EntityExtension::TARGET_PROPERTY, $property);
                }

                break;

            case EntityExtension::TARGET_PROPERTY:

                if (($classMetadata->associationMappings[$targetValue]["type"] & $this->associations) == 0) {
                    return;
                }

                $classMetadata->associationMappings[$targetValue]["cache"] = $classMetadata->associationMappings[$targetValue]["cache"] ?? [
                    "usage" => $usage,
                    "region" => $this->getRegionProperty($classMetadata, $targetValue),
                ];

                break;
        }
    }

    public function payload(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $target, ?string $targetValue = null)
    {
        if ($targetValue) {

            $cache = $this->getEntityManager()->getCache();
            if (!$cache) {
                return;
            }

            if (!$this->getClassMetadataManipulator()->hasAssociation($target, $targetValue)) {
                return;
            }

            if ($this->getClassMetadataManipulator()->isToManySide($target, $targetValue)) {
                $cache->evictCollection($classMetadata->getName(), $targetValue, $target->getId());
                return;
            }

            $cache->evictEntity($classMetadata->getName(), $target->getId());
            return;
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($classMetadata->associationMappings as $property => $associationMapping) {

            $isTargetEntityCached = !empty($this->getAnnotationReader()->getClassAnnotations($associationMapping["targetEntity"], self::class));
            if (!$isTargetEntityCached) {
                continue;
            }

            $propertyAccessor->getValue($target, $property); // readout value
            $this->payload($event, $classMetadata, $target, $property);
        }
    }

    public function prePersist(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->payload($event, $classMetadata, $entity, $property);
    }

    public function preUpdate(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->payload($event, $classMetadata, $entity, $property);
    }

    public function preRemove(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->payload($event, $classMetadata, $entity, $property);
    }
}
