<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\TranslationInterface;
use Base\Database\Walker\TranslatableWalker;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

use function is_file;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("class", type = "string")
 * })
 */
class Associate extends AbstractAnnotation
{
    public mixed $metadata;

    public function __construct(array $data = [])
    {
        $this->metadata = $data["metadata"] ?? [];
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->preLifecycleEvent($event, $classMetadata, $entity, $property);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->preLifecycleEvent($event, $classMetadata, $entity, $property);
    }

    public function preLifecycleEvent(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $value = $propertyAccessor->getValue($entity, $property);

        if (is_array($value)) {
            $reference = first($value);
        } else {
            $reference = $value;
        }

        $metadata = null;
        if ($this->getClassMetadataManipulator()->isEntity($reference)) {
            $metadata = $this->getClassMetadata($this->getClassMetadata($reference)->rootEntityName);
        }

        if ($metadata) {
            $reference = class_exists($this->metadata) ? $this->metadata : $metadata->name;
            if ($metadata->name == $reference) {
                if (is_array($value)) {
                    $value = array_map(fn($v) => is_object($v) ? $v->getId() : $v, $value);
                } else {
                    $value = is_object($value) ? $value->getId() : $value;
                }
                $propertyAccessor->setValue($entity, $property, $value);
            }
        }

        if ($this->metadata && $propertyAccessor->isReadable($entity, $this->metadata)) {
            $propertyAccessor->setValue($entity, $this->metadata, $metadata?->name);
        }
    }

    public function postUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->postLifecycleEvent($event, $classMetadata, $entity, $property);
    }

    public function postPersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->postLifecycleEvent($event, $classMetadata, $entity, $property);
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->postLifecycleEvent($event, $classMetadata, $entity, $property);
    }

    public function postLifecycleEvent(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $className = null;
        if ($this->metadata && $propertyAccessor->isReadable($entity, $this->metadata)) {
            $className = $propertyAccessor->getValue($entity, $this->metadata);
        }

        $repository = $this->getClassMetadataManipulator()->isEntity($className) ? $this->getRepository($this->getClassMetadata($className)->rootEntityName) : null;
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
