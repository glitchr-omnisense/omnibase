<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Base\Database\Entity\EntityExtensionInterface;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS"})
 */

#[\Attribute(\Attribute::TARGET_CLASS)]
class Logging extends AbstractAnnotation implements EntityExtensionInterface
{
    /**
     * @var bool
     */
    protected ?bool $verbosity;

    public function __construct(?string $verbosity = null)
    {
        $this->verbosity = $verbosity;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_CLASS);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public static array $trackedEntities = [];

    public static function get(): array
    {
        return self::$trackedEntities;
    }

    public static function has(string $entity, ?string $property = null): bool
    {
        return array_key_exists($entity, self::$trackedEntities);
    }

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
