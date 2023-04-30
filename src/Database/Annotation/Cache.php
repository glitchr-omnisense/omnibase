<?php

namespace Base\Database\Annotation;

use Attribute;
use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\TranslatableInterface;
use Base\Entity\Extension\Ordering;
use Base\Enum\EntityAction;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\Persistence\Event\LifecycleEventArgs as BaseLifecycleEventArgs;
use Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Caching to an entity or a collection.
 *
 * @Annotation
 * @NamedArgumentConstructor()
 * @Target({"CLASS","PROPERTY"})
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
final class Cache extends AbstractAnnotation
{
    /**
     * @Enum({"READ_ONLY", "NONSTRICT_READ_WRITE", "READ_WRITE"})
     * @var string The concurrency strategy.
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

    public const ONE_TO_ONE  = "ONE_TO_ONE" ;
    public const MANY_TO_ONE = "MANY_TO_ONE";
    public const ONE_TO_MANY = "ONE_TO_MANY";
    public const MANY_TO_MANY  = "ONE_TO_ONE" ;

    public const TO_ONE      = "TO_ONE";
    public const TO_MANY     = "TO_MANY";
    public const ALL         = "ALL";
    public const NONE         = "NONE";

    public function __construct(string $usage = 'READ_ONLY', ?string $region = null, ?string $associations = null)
    {
        $this->usage  = $usage;
        $this->region = $region;

        switch($associations) {
            case self::ONE_TO_ONE:
                $this->associations = ClassMetadataInfo::ONE_TO_ONE;
                break;
            case self::MANY_TO_ONE:
                $this->associations = ClassMetadataInfo::MANY_TO_ONE;
                break;
            case self::ONE_TO_MANY:
                $this->associations = ClassMetadataInfo::ONE_TO_MANY;
                break;
            case self::MANY_TO_MANY:
                $this->associations = ClassMetadataInfo::MANY_TO_MANY;
                break;
            case self::TO_MANY:
                $this->associations = ClassMetadataInfo::ONE_TO_MANY + ClassMetadataInfo::MANY_TO_MANY;
                break;
            case self::TO_ONE:
                $this->associations = ClassMetadataInfo::ONE_TO_ONE + ClassMetadataInfo::MANY_TO_ONE;
                break;
            case self::ALL:
                $this->associations = ClassMetadataInfo::ONE_TO_ONE + ClassMetadataInfo::MANY_TO_ONE + ClassMetadataInfo::ONE_TO_MANY + ClassMetadataInfo::MANY_TO_MANY;
                break;

            default:
            case self::NONE:
                $this->associations = 0;
        }
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_CLASS || $target == AnnotationReader::TARGET_PROPERTY);
    }

    public function getRegion(ClassMetadata $classMetadata)
    {
        return $this->region ?? $this->getEntityManager()->getConfiguration()->getNamingStrategy()->classToTableName($classMetadata->rootEntityName);
    }

    public function getRegionProperty(ClassMetadata $classMetadata, string $property)
    {
        return $this->getRegion($classMetadata)."__".$property;
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, string $targetValue = null)
    {
        $region = $this->getRegion($classMetadata);

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

            case AnnotationReader::TARGET_CLASS:

                $classMetadata->cache = [
                    "usage" => $usage,
                    "region" => $region,
                ];

                foreach ($classMetadata->associationMappings as $property => $associationMapping) {
                    $isTargetEntityCached = !empty($this->getAnnotationReader()->getClassAnnotations($associationMapping["targetEntity"], self::class));
                    if (!$isTargetEntityCached) {
                        continue;
                    }

                    $this->loadClassMetadata($classMetadata, AnnotationReader::TARGET_PROPERTY, $property);
                }

                break;

            case AnnotationReader::TARGET_PROPERTY:

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
        if($targetValue) {

            $cache = $this->getEntityManager()->getCache();
            if(!$cache) {
                return;
            }

            if(!$this->getClassMetadataManipulator()->hasAssociation($target, $targetValue)) {
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

            $value = $propertyAccessor->getValue($target, $property);
            $this->payload($event, $classMetadata, $target, $property);
        }
    }

    public function prePersist(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $target, ?string $targetValue = null)
    {
        return $this->payload($event, $classMetadata, $target, $targetValue);
    }

    public function preUpdate(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $target, ?string $targetValue = null)
    {
        return $this->payload($event, $classMetadata, $target, $targetValue);
    }

    public function preRemove(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $target, ?string $targetValue = null)
    {
        return $this->payload($event, $classMetadata, $target, $targetValue);
    }
}
