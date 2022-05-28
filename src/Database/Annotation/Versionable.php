<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Factory\EntityExtensionInterface;
use Base\Entity\Extension\Revision;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Versionable extends AbstractAnnotation implements EntityExtensionInterface
{
    public function supports(string $target, ?string $targetValue = null, $entity = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public static $trackedColumns   = [];
    public static function get():array { return self::$trackedColumns; }
    public static function has(string $className, ?string $property = null):bool { return isset(self::$trackedColumns[$className]) && in_array($property, self::$trackedColumns[$className]); } 

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    {
        $reflProperty = $classMetadata->getReflectionClass()->getProperty($targetValue);
        if($reflProperty->getDeclaringClass()->getName() == $classMetadata->getName()) {

            self::$trackedColumns[$classMetadata->getName()]   = self::$trackedColumns[$classMetadata->getName()] ?? [];
            self::$trackedColumns[$classMetadata->getName()][] = $targetValue;
        }
    }

    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        $revision = new Revision();
        return [$revision];
    }
}
