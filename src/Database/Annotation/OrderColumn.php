<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Common\Collections\OrderedArrayCollection;
use Base\Database\Entity\EntityExtensionInterface;
use Base\Database\Type\SetType;
use Base\Entity\Extension\Ordering;
use Base\Entity\User;
use Base\Enum\EntityAction;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\ArrayType;
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
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("order", type = "string"),
 * })
 */
class OrderColumn extends AbstractAnnotation implements EntityExtensionInterface
{
    public const ASC  = "ASC";
    public const DESC = "DESC";

    public string $order;

    public function __construct( array $data = [] )
    {
        $this->order = $data['order'] ?? self::ASC;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        if($object instanceof ClassMetadata) {

            $type = $this->getClassMetadataManipulator()->getTypeOfField($object, $targetValue);
            $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);

            $isArray = is_instanceof($doctrineType, ArrayType::class) || is_instanceof($doctrineType, JsonType::class);
            $isToMany = $this->getClassMetadataManipulator()->isToManySide($object, $targetValue);
            // SetType is not supported yet.. There is a sorting issue with Select2
             $isSet  = is_instanceof($doctrineType, SetType::class);

            if(!$isSet && !$isArray && !$isToMany)
                return false;

            $siblingAnnotations = $this->getAnnotationReader()->getDefaultPropertyAnnotations($object->getName(), OrderBy::class);
            if(array_key_exists($targetValue, $siblingAnnotations))
                throw new \Exception("@OrderBy annotation is in conflict with @OrderColum for \"".$object->getName()."::$targetValue\"");
        }

        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public static $orderedColumns   = [];
    public static function get():array { return self::$orderedColumns; }
    public static function has(string $className, ?string $property = null): bool { return isset(self::$orderedColumns[$className]) && in_array($property, self::$orderedColumns[$className]); }

    protected $ordering = [];
    public function getOrderedColumns(mixed $entity)
    {
        $orderedColumns = [];
        foreach(self::$orderedColumns as $column) {

            list($className, $_) = explode("::", $column);
            if(is_instanceof($entity, $className))
                $orderedColumns[] = $column;
        }

        return $orderedColumns;
    }

    public function addOrderedColumnIfNotSet(ClassMetadata $classMetadata, ?string $property = null)
    {
        $className = $classMetadata->getReflectionProperty($property)->getDeclaringClass()->getName();
        if(!in_array($className."::".$property, self::$orderedColumns))
            self::$orderedColumns[] = $className."::".$property;
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $property = $this->getClassMetadataManipulator()->getFieldName($entity, $property) ?? $property;
        $this->addOrderedColumnIfNotSet($classMetadata, $property);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $orderingRepository = $this->getRepository(Ordering::class);

        $className = array_transforms(function ($k, $e) use ($property): ?array {

            list($c, $p) = explode("::", $e);
            return $p === $property ? [$k, $c] : null;

        }, $this->getOrderedColumns($entity));

        $className = first($className);
        if ($className === null) return;

        try { $entityValue = $classMetadata->getFieldValue($entity, $property); }
        catch (Exception $e) { return; }

        $shift = 0;
        $orderedIndexes = $orderingRepository->cacheOneByEntityIdAndEntityClass($entity->getId(), $className);
        $orderedIndexes = $orderedIndexes?->getEntityData()[$property] ?? [];
        $orderedIndexes = array_transforms(function ($k, $v) use (&$shift): ?array {

            if (is_int($v))
                return [$k - $shift, $v];

            $shift++;
            return null;

        }, array_values($orderedIndexes));

        $nEntries = $entityValue instanceof Collection ? $entityValue->count() : count($entityValue ?? []);
        while(count($orderedIndexes) < $nEntries)
            $orderedIndexes[] = count($orderedIndexes);

        if(is_array($entityValue)) {

            $entityValue = array_flip(array_transforms(fn($k,$v):array => [$entityValue[$k],$v], $orderedIndexes));
            ksort($entityValue);

            if ($this->order == "DESC") $entityValue = array_reverse($entityValue);

            $propertyAccessor->setValue($entity, $property, $entityValue);

        } else if($entityValue instanceof PersistentCollection && $entityValue->getOwner() == $entity) {

            $orderedIndexes = $this->order == "DESC" ? array_reverse($orderedIndexes) : $orderedIndexes;

            $reflProp = new ReflectionProperty(PersistentCollection::class, "collection");
            $reflProp->setAccessible(true);

            $reflProp->setValue($entityValue, new OrderedArrayCollection($entityValue->unwrap() ?? [], $orderedIndexes));
        }
    }

    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        $cache = $this->getEntityManager()->getCache();
        $orderingRepository = $this->getEntityManager()->getRepository(Ordering::class);
        $classMetadataManipulator = $this->getClassMetadataManipulator();

        $id = spl_object_id($entity);
        switch($action) {

            case EntityAction::INSERT:
            case EntityAction::UPDATE:

                $propertyAccessor = PropertyAccess::createPropertyAccessor();

                $data = [];
                foreach($properties as $property) {

                    //NB: Evict in AbstractExtension doesn't seems to be working.. TBC
                    if($cache) {

                        $cache->evictEntity($className, $entity->getId());
                        if ($this->getClassMetadata($className)->hasAssociation($property))
                            $cache->evictCollection($className, $property, $entity->getId());
                    }

                    $value = $propertyAccessor->getValue($entity, $property);
                    if($classMetadataManipulator->hasField($className, $property) && !$classMetadataManipulator->hasAssociation($className, $property)) {

                        $fieldType = $classMetadataManipulator->getTypeOfField($className, $property);
                        $doctrineType = $classMetadataManipulator->getDoctrineType($fieldType);

                        if($classMetadataManipulator->isSetType($doctrineType))
                            $data[$property] = array_values(array_flip($doctrineType::getOrderingKeys($value)));

                    } else if($value instanceof Collection) {

                        $data[$property] = $value->toArray();
                        $nData = count($data[$property]);

                        $dataIdentifier = array_filter(array_map(fn($e) => $e->getId(), $data[$property]));
                        uasort($dataIdentifier, fn($a,$b) => $a === null ? 1 : ($b === null ? -1 : ($a < $b ? -1 : 1)));

                        $data[$property] = array_flip($dataIdentifier);
                        ksort($data[$property]);

                        $data[$property] = array_pad(array_values($data[$property]), $nData, null);
                    }

                    if(array_key_exists($property, $data) && is_identity($data[$property]))
                        unset($data[$property]);
                }

                if(!array_key_exists($className, $this->ordering)) $this->ordering[$className] = [];
                $this->ordering[$className][$id] ??= $orderingRepository->cacheOneByEntityIdAndEntityClass($entity->getId(), $className);
                $this->ordering[$className][$id] ??= $this->ordering[$className][$id] = new Ordering();
                $this->ordering[$className][$id]->setEntityData($data);

                $orderingId = $this->ordering[$className][$id]->getId();
                if($orderingId) $cache->evictEntity(Ordering::class, $orderingId);

            case EntityAction::DELETE:
                break;

            default:
                throw new Exception("Unknown action \"$action\" passed to ".__CLASS__);
        }

        return isset($this->ordering[$className][$id]) ? [$this->ordering[$className][$id]] : [];
    }
}
