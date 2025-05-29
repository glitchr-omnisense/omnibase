<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Annotation\Extension\ExtensionInlineInterface;
use Base\Database\Type\SetType;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OrderBy;
use Exception;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY"})
 */

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OrderColumnNew extends AbstractAnnotation implements ExtensionInlineInterface
{
    public const ASC = "ASC";
    public const DESC = "DESC";

    public string $sort;
    public string $orderBy;

    public function __construct(string $orderBy, string $sort = self::ASC)
    {
        $this->sort = $sort;
        $this->orderBy = $orderBy;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     * @throws Exception
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        if ($object instanceof ClassMetadata) {
            $type = $this->getClassMetadataManipulator()->getTypeOfField($object, $targetValue);
            $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);

            $isArray = is_instanceof($doctrineType, JsonType::class);
            $isToMany = $this->getClassMetadataManipulator()->isToManySide($object, $targetValue);
            $isSet = is_instanceof($doctrineType, SetType::class);

            if (!$isSet && !$isArray && !$isToMany) {
                return false;
            }

            $siblingAnnotations = $this->getAnnotationReader()->getPropertyAnnotations($object->getName(), OrderBy::class);
            if (array_key_exists($targetValue, $siblingAnnotations)) {
                throw new Exception("@OrderBy metadata is in conflict with @OrderColum for \"" . $object->getName() . "::$targetValue\"");
            }
        }

        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public static array $orderedColumns = [];

    public static function get(): array
    {
        return self::$orderedColumns;
    }

    public static function has(string $className, ?string $property = null): bool
    {
        if( array_key_exists($className, self::$orderedColumns) ) {

            return $property === null || in_array($property, self::$orderedColumns[$className]);
        }

        return false;
    }

    protected array $ordering = [];

    /**
     * @param mixed $entity
     * @return array
     * @throws Exception
     */
    public function getOrderedColumns(mixed $entity, ?string $property = null): array
    {
        $orderedColumns = [];
        foreach ($this->get() as $column) {

            list($className, $classProperty) = explode("::", $column);
            if($property !== null && $property != $classProperty) continue;

            if (is_instanceof($entity, $className)) {

                $orderedColumns[$className] ??= [];
                $orderedColumns[$className][] = $classProperty;
            }
        }

        return $orderedColumns;
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        // if (!$this->supports($target, $targetValue, $classMetadata)) return;
            
        // $property = $classMetadata->getFieldMapping($targetValue);
        // $type = $this->getClassMetadataManipulator()->getTypeOfField($classMetadata, $targetValue);

        // if (is_instanceof($type, JsonType::class) || is_instanceof($type, SetType::class)) {
        //     self::$orderedColumns[$classMetadata->getName()][] = $targetValue;
        //     $this->ordering[$property] = $this->orderBy;
        // }
    }
}
