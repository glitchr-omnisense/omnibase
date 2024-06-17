<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Common\Collections\OrderedArrayCollection;
use Base\Database\Entity\EntityExtensionInterface;
use Base\Database\Type\SetType;
use Base\Entity\Extension\Ordering;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Base\Enum\EntityAction;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\PersistentCollection;
use Exception;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY"})
 */

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OrderColumn extends AbstractAnnotation implements EntityExtensionInterface
{
    public const ASC = "ASC";
    public const DESC = "DESC";

    public string $order;

    public function __construct(string $order = self::ASC)
    {
        $this->order = $order;
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

    public function addOrderedColumnIfNotSet(ClassMetadata $classMetadata, ?string $property = null)
    {
        $className = $classMetadata->getReflectionProperty($property)->getDeclaringClass()->getName();
        if (!in_array($className . "::" . $property, self::$orderedColumns)) {
            self::$orderedColumns[] = $className . "::" . $property;
        }
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $property = $this->getClassMetadataManipulator()->getFieldName($entity, $property) ?? $property;
        $this->addOrderedColumnIfNotSet($classMetadata, $property);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $orderingRepository = $this->getRepository(Ordering::class);

        $className = first(array_keys($this->getOrderedColumns($entity, $property)));
        if ($className === null) { return; }

        try { $entityValue = $classMetadata->getFieldValue($entity, $property) ?? []; }
        catch (Exception $e) { return; }

        $shift = 0;
        $orderedIndexes = $orderingRepository->cacheOneByEntityIdAndEntityClass($entity->getId(), $className);
        $orderedIndexes = $orderedIndexes?->getEntityData()[$property] ?? [];
        $orderedIndexes = array_transforms(function ($k, $v) use (&$shift): ?array {

            if (is_int($v)) {
                return [$k - $shift, $v];
            }

            $shift++;
            return null;

        }, array_values($orderedIndexes));
        
        if (is_array($entityValue)) {
            
            if($orderedIndexes) $entityValue = array_flip(array_transforms(fn($k, $v): array => [$entityValue[$k], $v], $orderedIndexes));
            ksort($entityValue);

            if ($this->order == "DESC") {
                $entityValue = array_reverse($entityValue);
            }

            $propertyAccessor->setValue($entity, $property, $entityValue);

        } elseif ($entityValue instanceof PersistentCollection && $entityValue->getOwner() == $entity) {

            $orderedIndexes = $this->order == "DESC" ? array_reverse($orderedIndexes) : $orderedIndexes;

            $reflProp = new ReflectionProperty(PersistentCollection::class, "collection");
            $reflProp->setAccessible(true);
            $reflProp->setValue($entityValue, new OrderedArrayCollection($entityValue ?? [], $orderedIndexes));
        }
    }

    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        $cache = $this->getEntityManager()->getCache();
        $orderingRepository = $this->getEntityManager()->getRepository(Ordering::class);
        $classMetadataManipulator = $this->getClassMetadataManipulator();

        $id = spl_object_id($entity);
        switch ($action) {
            case EntityAction::INSERT:
            case EntityAction::UPDATE:

                $propertyAccessor = PropertyAccess::createPropertyAccessor();

                $data = [];
                foreach ($properties as $property) {
                    //NB: Evict in AbstractExtension doesn't seems to be working.. TBC
                    if ($cache) {
                        $cache->evictEntity($className, $entity->getId());
                        if ($this->getClassMetadata($className)->hasAssociation($property)) {
                            $cache->evictCollection($className, $property, $entity->getId());
                        }
                    }

                    $value = $propertyAccessor->getValue($entity, $property);
                    if ($classMetadataManipulator->hasField($className, $property) && !$classMetadataManipulator->hasAssociation($className, $property)) {
                        $fieldType = $classMetadataManipulator->getTypeOfField($className, $property);
                        $doctrineType = $classMetadataManipulator->getDoctrineType($fieldType);

                        if ($classMetadataManipulator->isSetType($doctrineType)) {
                            $data[$property] = array_values(array_flip($doctrineType::getOrderingKeys($value)));
                        }
                    } elseif ($value instanceof Collection) {
                        $data[$property] = $value->toArray();
                        $nData = count($data[$property]);

                        $dataIdentifier = array_filter(array_map(fn($e) => $e->getId(), $data[$property]));
                        uasort($dataIdentifier, fn($a, $b) => $a === null ? 1 : ($b === null ? -1 : ($a < $b ? -1 : 1)));

                        $data[$property] = array_flip($dataIdentifier);
                        ksort($data[$property]);

                        $data[$property] = array_pad(array_values($data[$property]), $nData, null);
                    }

                    if (array_key_exists($property, $data) && is_identity(array_filter($data[$property]))) {
                        unset($data[$property]);
                    }
                }

                if (!array_key_exists($className, $this->ordering)) {
                    $this->ordering[$className] = [];
                }

                $this->ordering[$className][$id] ??= $orderingRepository->findOneByEntityIdAndEntityClass($entity->getId(), $className);
                if ($this->ordering[$className][$id] !== null && empty($data)) {
                    $this->ordering[$className][$id]->setEntityData([]);

                    $orderingId = $this->ordering[$className][$id]->getId();
                    if ($orderingId) {
                        $cache->evictEntity(Ordering::class, $orderingId);
                    }
                } elseif (!empty($data)) {
                    $this->ordering[$className][$id] ??= new Ordering();
                    $this->ordering[$className][$id]->setEntityData($data);

                    $orderingId = $this->ordering[$className][$id]->getId();
                    if ($orderingId) {
                        $cache->evictEntity(Ordering::class, $orderingId);
                    }
                }

            // no break
            case EntityAction::DELETE:
                break;

            default:
                throw new Exception("Unknown action \"$action\" passed to " . __CLASS__);
        }

        return isset($this->ordering[$className][$id]) ? [$this->ordering[$className][$id]] : [];
    }
}
