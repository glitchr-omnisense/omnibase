<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Entity\EntityExtensionInterface;

/**
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("verbosity", type = "string"),
 * })
 */
class Logging extends AbstractAnnotation implements EntityExtensionInterface
{
    /**
     * @var bool
     */
    protected ?bool $verbosity;

    public function __construct( array $data = [])
    {
        $this->verbosity = $data['verbosity'] ?? null;
    }

    public function supports(string $target, ?string $targetValue = null, $entity = null): bool
    {
        return ($target == AnnotationReader::TARGET_CLASS);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public static $trackedEntities   = [];
    public static function get():array { return self::$trackedEntities; }
    public static function has(string $className, ?string $property = null): bool { return array_key_exists($className, self::$trackedEntities); }

    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        $log = null; //new Log([], null);
        // TO DO
        return [$log];
    }

    // @TODO NOT CALLED IN PRODUCTION BECAUSE OF CACHE
    // public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    // {
    //     $reflProperty = $classMetadata->getReflectionClass()->getProperty($targetValue);
    //     if($reflProperty->getDeclaringClass()->getName() == $classMetadata->getName()) {

    //         // $classMetadata->setField
    //         self::$trackedEntities[$classMetadata->getName()]   = self::$trackedEntities[$classMetadata->getName()] ?? [];
    //         self::$trackedEntities[$classMetadata->getName()][] = $targetValue;
    //     }
    // }
}
