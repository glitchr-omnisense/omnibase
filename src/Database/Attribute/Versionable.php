<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAnnotation;
use Base\Database\Entity\EntityExtension;
use Base\Database\Attribute\Extension\ExtensionMetadataInterface;
use Base\Entity\Extension\Revision;


#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Versionable extends AbstractAnnotation implements ExtensionMetadataInterface
{
    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == EntityExtension::TARGET_PROPERTY);
    }

    public static $trackedColumns = []; // @TODO TO BE IMPLEMENTED

    public static function get(): array
    {
        return self::$trackedColumns;
    }

    public static function has(string $className, ?string $property = null): bool
    {
        return isset(self::$trackedColumns[$className]) && in_array($property, self::$trackedColumns[$className]);
    }

    // public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    // {
    //     $reflProperty = $classMetadata->getReflectionClass()->getProperty($targetValue);
    //     if($reflProperty->getDeclaringClass()->getName() == $classMetadata->getName()) {

    //         self::$trackedColumns[$classMetadata->getName()]   = self::$trackedColumns[$classMetadata->getName()] ?? [];
    //         self::$trackedColumns[$classMetadata->getName()][] = $targetValue;
    //     }
    // }

    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        $revision = new Revision();
        return [$revision];
    }
}
