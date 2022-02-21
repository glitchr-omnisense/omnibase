<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Factory\EntityExtensionInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("verbosity", type = "string"),
 * })
 */
class Logging extends AbstractAnnotation implements EntityExtensionInterface
{
    public function __construct( array $data = [])
    {
        $this->verbosity = $data['verbosity'] ?? null;
    }

    public function supports(string $target, ?string $targetValue = null, $entity = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public static $trackedColumns   = [];
    public static function get():array { return self::$trackedColumns; }
    public static function has($entity, $property):bool { return isset(self::$trackedColumns[$entity]) && in_array($property, self::$trackedColumns[$entity]); } 
    
    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        $log = null; //new Log([], null);
        // TO DO
        return [$log];
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    {
        $reflProperty = $classMetadata->getReflectionClass()->getProperty($targetValue);
        if($reflProperty->getDeclaringClass()->getName() == $classMetadata->getName()) {

            // $classMetadata->setField
            self::$trackedColumns[$classMetadata->getName()]   = self::$trackedColumns[$classMetadata->getName()] ?? [];
            self::$trackedColumns[$classMetadata->getName()][] = $targetValue;
        }
    }
}
