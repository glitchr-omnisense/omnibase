<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Base\Database\Entity\EntityExtension;
use Doctrine\ORM\Mapping\ClassMetadata;
use Base\Database\Attribute\Backend\CacheBackend;
use Omnibridge\Persistence\Lifecycle;

/**
 * Caching to an entity or a collection.
 */

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class Cache extends AbstractAttribute implements ExtensionOptionInterface
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
    public const MANY_TO_MANY = "MANY_TO_MANY";

    /**
     * The association types as Doctrine's ClassMetadata numbers them (ONE_TO_ONE = 1, MANY_TO_ONE = 2,
     * ONE_TO_MANY = 4, MANY_TO_MANY = 8), so that the attribute is made without Doctrine loaded.
     */
    public const FLAG_ONE_TO_ONE = 1;
    public const FLAG_MANY_TO_ONE = 2;
    public const FLAG_ONE_TO_MANY = 4;
    public const FLAG_MANY_TO_MANY = 8;

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
                $this->associations = self::FLAG_ONE_TO_ONE;
                break;
            case self::MANY_TO_ONE:
                $this->associations = self::FLAG_MANY_TO_ONE;
                break;
            case self::ONE_TO_MANY:
                $this->associations = self::FLAG_ONE_TO_MANY;
                break;
            case self::MANY_TO_MANY:
                $this->associations = self::FLAG_MANY_TO_MANY;
                break;
            case self::TO_MANY:
                $this->associations = self::FLAG_ONE_TO_MANY + self::FLAG_MANY_TO_MANY;
                break;
            case self::TO_ONE:
                $this->associations = self::FLAG_ONE_TO_ONE + self::FLAG_MANY_TO_ONE;
                break;
            case self::ALL:
                $this->associations = self::FLAG_ONE_TO_ONE + self::FLAG_MANY_TO_ONE + self::FLAG_ONE_TO_MANY + self::FLAG_MANY_TO_MANY;
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
                    $isTargetEntityCached = !empty($this->getAttributeReader()->getClassAttributes($associationMapping["targetEntity"], self::class));
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

    /**
     * The neutral hooks (plan "omnibase agnostic", step F2b): what the persistence's cache keeps of the
     * entity, or of the property's association, dropped through its CacheBackend (Doctrine's
     * second-level cache; nothing for a persistence without one).
     */
    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
        $this->backend($event, CacheBackend::class)->evict($this, $event->entity, $property);
    }

    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
        $this->backend($event, CacheBackend::class)->evict($this, $event->entity, $property);
    }

    public function onPreRemove(Lifecycle\PreRemove $event, ?string $property = null): void
    {
        $this->backend($event, CacheBackend::class)->evict($this, $event->entity, $property);
    }
}
