<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Annotation\Extension\ExtensionInlineInterface;
use Base\Database\Event\DoctrineQueryEventArgs;
use Base\Database\Type\SetType;
use Base\Database\Walker\OrderByWalker;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\Query;
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

            $isSet = is_instanceof($doctrineType, SetType::class);
            $isArray = is_instanceof($doctrineType, JsonType::class);
            $isToMany = $this->getClassMetadataManipulator()->isToManySide($object, $targetValue);

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
        if (!$this->supports($target, $targetValue, $classMetadata)) return;

        // Check if orderBy is mapped as JSON
        $orderByType = $this->getClassMetadataManipulator()->getTypeOfField($classMetadata, $this->orderBy);
        $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($orderByType);

        if ($doctrineType instanceof JsonType || $doctrineType === 'json' || $doctrineType === JsonType::class) {
            throw new Exception("The 'orderBy' field '{$this->orderBy}' cannot be of type JSON.");
        }

        // Map the orderBy column if not already mapped
        if (!$classMetadata->hasField($this->orderBy)) {
            $classMetadata->mapField([
                'fieldName' => $this->orderBy,
                'type' => $orderByType,
            ]);
        }
    }

    public function onQuery(DoctrineQueryEventArgs $args): void
    {
        $classMetadata = $args->getClassMetadata();
        $fields = array_merge($classMetadata->getFieldNames(), $classMetadata->getAssociationNames());

        foreach ($fields as $field) {

            $annotations = $this->getAnnotationReader()->getPropertyAnnotations($classMetadata->getName(), self::class);
            if (isset($annotations[$field])) {

                $annotation = last($annotations[$field]);
                $orderBy = $annotation->orderBy;
                $sort = $annotation->sort ?? self::ASC;

                $existingOrder = $args->getQuery()->getHint(OrderByWalker::HINT_ORDER_ARRAY);
                if(!$existingOrder) $existingOrder = [];
                $newOrder = array_merge($existingOrder, [$field => [$orderBy, $sort]]);
                $args->getQuery()->setHint(OrderByWalker::HINT_ORDER_ARRAY, $newOrder);
                
                $existingOrder = $args->getQuery()->getHint(Query::HINT_CUSTOM_TREE_WALKERS);
                if(!$existingOrder) $existingOrder = [];
                $newOrder = array_merge($existingOrder, [OrderByWalker::class]);
                $args->getQuery()->setHint(Query::HINT_CUSTOM_TREE_WALKERS, $newOrder);
            }
        }
    }

    public function postQuery(DoctrineQueryEventArgs $args): void
    {
        dump($args->getQuery()->getSQL());
        exit(1);
    }
}