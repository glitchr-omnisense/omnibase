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
class OrderColumn extends AbstractAnnotation implements EntityExtensionInterface
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
    public static $orderedColumns   = [];
    public static function get() { return self::$orderedColumns; }
    public static function has($entity, $property):bool { return isset(self::$orderedColumns[$entity]) && in_array($property, self::$orderedColumns[$entity]); } 
    
    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    {
        $reflProperty = $classMetadata->getReflectionClass()->getProperty($targetValue);
        if($reflProperty->getDeclaringClass()->getName() == $classMetadata->getName()) {

            // $classMetadata->setField
            self::$orderedColumns[$classMetadata->getName()]   = self::$orderedColumns[$classMetadata->getName()] ?? [];
            self::$orderedColumns[$classMetadata->getName()][] = $targetValue;
        }
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        // dump("PRE PERSIST !");
        // exit(1);
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        // dump("PRE UPDATE !");
        // exit(1);
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        // dump("POST LOAD SORTING.. ".$property);
        // dump($entity, $property, $classMetadata->getPropertyValue($entity, $classMetadata->getFieldName($property)));
        // $iterator = $collection->getIterator();
        // $iterator->uasort(function ($a, $b) {
        //     return ($a->getPropery() < $b->getProperty()) ? -1 : 1;
        // });
        // $collection = new ArrayCollection(iterator_to_array($iterator));

        // dump($entity);
    }

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        // dump("FLUSH ORDER COLUMN.. ".$property);
        // dump($this->getOldEntity($entity));
        // dump($entity);
        
        // exit(1);
    }
}
