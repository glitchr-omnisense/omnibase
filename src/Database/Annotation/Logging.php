<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Annotation\EntityExtensionInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("reference", type = "string"),
 *   @Attribute("disable",   type = "boolean"),
 * })
 */
class Logging extends AbstractAnnotation implements EntityExtensionInterface
{
    protected ?string $referenceColumn;

    public function __construct( array $data )
    {
        $this->referenceColumn = $data['reference'] ?? null;
        $this->disable = $data['disable'] ?? null;
    }

    public function getReferenceColumn()
    {
        return $this->referenceColumn;
    }

    public function supports(string $target, ?string $targetValue = null, $entity = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public static $trackedColumns   = [];
    public static function get() { return self::$trackedColumns; }
    public static function has($entity, $property):bool { return isset(self::$trackedColumns[$entity]) && in_array($property, self::$trackedColumns[$entity]); } 
    
    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    {
        $reflProperty = $classMetadata->getReflectionClass()->getProperty($targetValue);
        if($reflProperty->getDeclaringClass()->getName() == $classMetadata->getName()) {

            // $classMetadata->setField
            self::$trackedColumns[$classMetadata->getName()]   = self::$trackedColumns[$classMetadata->getName()] ?? [];
            self::$trackedColumns[$classMetadata->getName()][] = $targetValue;
        }
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
    }

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
    }
}
