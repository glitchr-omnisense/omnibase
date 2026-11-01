<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Base\Database\Mapping\EntityShapes;
use Omnibridge\Persistence\Lifecycle;
use Exception;

use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @Attributes({
 *   @Attribute("class", type = "string")
 * })
 */

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Associate extends AbstractAttribute implements ExtensionOptionInterface
{
    public string $metadata;

    public function __construct(string $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AttributeReader::TARGET_PROPERTY);
    }

    /**
     * The neutral hooks (plan "omnibase agnostic", step F2c): before a write, an associated entity kept as
     * its identifier and its root class; after, the entity read back by that identifier.
     */
    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
        $this->toIdentifier($event, $event->entity, $property);
    }

    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
        $this->toIdentifier($event, $event->entity, $property);
    }

    public function onPostUpdate(Lifecycle\PostUpdate $event, ?string $property = null): void
    {
        $this->toEntity($event, $event->entity, $property);
    }

    public function onPostPersist(Lifecycle\PostPersist $event, ?string $property = null): void
    {
        $this->toEntity($event, $event->entity, $property);
    }

    public function onPostLoad(Lifecycle\PostLoad $event, ?string $property = null): void
    {
        $this->toEntity($event, $event->entity, $property);
    }

    /** The root class of an entity (or an entity's class), null for anything else. */
    protected function rootOf(Lifecycle\EntityEvent $event, mixed $reference): ?string
    {
        if (!\is_object($reference) && !\is_string($reference)) {
            return null;
        }

        $shapes = $this->backend($event, EntityShapes::class);

        return $shapes->has($reference) ? $shapes->shape($reference)->rootClass() : null;
    }

    /** Formerly preLifecycleEvent(), which took Doctrine's event and metadata. */
    protected function toIdentifier(Lifecycle\EntityEvent $event, object $entity, ?string $property): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $value = $propertyAccessor->getValue($entity, $property);

        if (is_array($value)) {
            $reference = first($value);
        } else {
            $reference = $value;
        }

        $root = $this->rootOf($event, $reference);
        if ($root) {
            $reference = class_exists($this->metadata) ? $this->metadata : $root;
            if ($root == $reference) {
                if (is_array($value)) {
                    $value = array_map(fn($v) => is_object($v) ? $v->getId() : $v, $value);
                } else {
                    $value = is_object($value) ? $value->getId() : $value;
                }
                $propertyAccessor->setValue($entity, $property, $value);
            }
        }

        if ($this->metadata && $propertyAccessor->isReadable($entity, $this->metadata)) {
            $propertyAccessor->setValue($entity, $this->metadata, $root);
        }
    }

    /** Formerly postLifecycleEvent(), which took Doctrine's event and metadata. */
    protected function toEntity(Lifecycle\EntityEvent $event, object $entity, ?string $property): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $className = null;
        if ($this->metadata && $propertyAccessor->isReadable($entity, $this->metadata)) {
            $className = $propertyAccessor->getValue($entity, $this->metadata);
        }

        $root = $this->rootOf($event, $className);
        $repository = $root ? $this->getRepository($root) : null;
        if (!$repository) {
            return;
        }

        $value = $propertyAccessor->getValue($entity, $property);
        if (!$value) {
            return;
        }

        try {
            $value = is_array($value) ? $repository->cacheBy(["id" => $value])->getResult() : $repository->cacheOneBy(["id" => $value]);
        } catch (Exception $e) {
        }

        $propertyAccessor->setValue($entity, $property, $value);
        $propertyAccessor->setValue($entity, $this->metadata, null);
    }
}
