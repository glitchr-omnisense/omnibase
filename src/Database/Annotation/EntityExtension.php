<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("reference", type = "string"),
 *   @Attribute("disable",   type = "boolean"),
 * })
 */
class EntityExtension extends AbstractAnnotation
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
        return ($target == AnnotationReader::TARGET_CLASS);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public static $extendedEntities   = [];
    public static function get() { return self::$extendedEntities; }
    public static function has($entity):bool 
    {
        $className = is_object($entity) ? get_class($entity) : $entity;
        return in_array($className, self::$extendedEntities); 
    } 
    
    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    {
        self::$extendedEntities[] = $classMetadata->getName();
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
