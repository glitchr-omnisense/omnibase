<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Common\Collections\OrderedArrayCollection;
use Base\Database\Factory\EntityExtensionInterface;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Base\Entity\Extension\Ordering;
use Base\Enum\EntityAction;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\ArrayType;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
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

    public function __construct( array $data = [] )
    {
        $this->order          = $data['order'] ?? self::ASC;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        if($object instanceof ClassMetadata) {

            $type = $this->getClassMetadataManipulator()->getTypeOfField($object, $targetValue);
            $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);

            $isArray = is_instanceof($doctrineType, ArrayType::class);
            $isToMany = $this->getClassMetadataManipulator()->isToManySide($object, $targetValue);
            // SET is not supported yet.. Select2 issue sorting settype
            // $isSet  = is_instanceof($doctrineType, SetType::class);

            if(/*!$isSet &&*/ !$isArray && !$isToMany)
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
        $this->addOrderedColumnIfNotSet($classMetadata, $property);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $orderingRepository = $this->getRepository(Ordering::class);

        $className = array_transforms(function($k, $e) use ($property) : ?array {

            list($c, $p) = explode("::", $e);
            return $p === $property ? [$k, $c] : null;

        }, $this->getOrderedColumns($entity));

        $className = first($className);
        if($className === null) return;

        try { $entityValue = $propertyAccessor->getValue($entity, $property); }
        catch (Exception $e) { return; }

        $ordering = $orderingRepository->cacheOneByEntityIdAndEntityClass($entity->getId(), $className);
        if($ordering === null) return;

        $data = $ordering->getEntityData();
        $orderedIndexes = $data[$property] ?? [];
        if(is_array($entityValue)) {

            $entityValue = array_map(fn($k) => $entityValue[$k], $orderedIndexes);
            if($this->order == "DESC") $entityValue = array_reverse($entityValue);

            $propertyAccessor->setValue($entity, $property, $entityValue);

        } else if($entityValue instanceof PersistentCollection && $entityValue->getOwner() == $entity) {

            $reflProp = new ReflectionProperty(PersistentCollection::class, "collection");
            $reflProp->setAccessible(true);
            $reflProp->setValue($entityValue, new OrderedArrayCollection($entityValue->unwrap()->toArray() ?? [], $this->order == "DESC" ? array_reverse($orderedIndexes) : $orderedIndexes));
        }
    }

    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        $orderingRepository = $this->getEntityManager()->getRepository(Ordering::class);

        $id = spl_object_id($entity);
        switch($action) {

            case EntityAction::INSERT:
            case EntityAction::UPDATE:

                $propertyAccessor = PropertyAccess::createPropertyAccessor();

                $data = [];
                foreach($properties as $property) {

                    $value = $propertyAccessor->getValue($entity, $property);

                    // ARRAY ARE NOT SUPPORTED YET.. SOME TESTS PERFORMED WITH USER ROLE FOR INSTANCE..
                    /*if(is_array($value)) $data[$property] = array_order($value, $this->getOldEntity($entity)->getRoles());
                    else*/ if($value instanceof Collection) {

                        $data[$property] = $value->toArray();
                        $dataIdentifier = array_map(fn($e) => $e->getId(), $data[$property]);

                        uasort($dataIdentifier, fn($a,$b) => $a === null ? 1 : ($b === null ? -1 : ($a < $b ? -1 : 1)));

                        $data[$property] = array_flip(array_keys($dataIdentifier));
                        ksort($data[$property]);
                    }

                    if(array_key_exists($property, $data) && is_identity($data[$property]))
                        unset($data[$property]);
                }

                if(!array_key_exists($className, $this->ordering)) $this->ordering[$className] = [];
                $this->ordering[$className][$id] = $this->ordering[$className][$id] ?? $orderingRepository->findOneByEntityIdAndEntityClass($entity->getId(), $className);
                $this->ordering[$className][$id] = $this->ordering[$className][$id] ?? new Ordering();
                $this->ordering[$className][$id]->setEntityData($data);

                break;

            case EntityAction::DELETE:
                break;

            default:
                throw new Exception("Unknown action \"$action\" passed to ".__CLASS__);
        }

        return isset($this->ordering[$className][$id]) ? [$this->ordering[$className][$id]] : [];
    }
}
