<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Common\Collections\OrderedArrayCollection;
use Base\Database\Factory\EntityExtensionInterface;
use Base\Database\Type\EnumType;
use Base\Entity\Extension\Ordering;
use Base\Enum\EntityAction;
use Base\Traits\BaseTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\LifecycleEventArgs;
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
            
            $isEnum = is_instanceof($doctrineType, EnumType::class);
            $isToMany = $this->getClassMetadataManipulator()->isToManySide($object, $targetValue);

            if(!$isEnum && !$isToMany)
                throw new \Exception("Unexpected column type found for @OrderColumn \"".$targetValue."\" found in \"".$object->getName()."\": expecting \"Collection\" or \"array\"");
                
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
    public function getOrderedColumns($entity)
    {
        $orderedColumns = [];
        foreach(self::$orderedColumns as $column) {

            list($className, $_) = explode("::", $column);
            if(is_instanceof($entity, $className)) 
                $orderedColumns[] = $column;
        }

        return $orderedColumns;
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    {
        $reflProperty = $classMetadata->getReflectionClass()->getProperty($targetValue);
        if($reflProperty->getDeclaringClass()->getName() == $classMetadata->getName())
            self::$orderedColumns[] = $classMetadata->getName()."::".$targetValue;
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        
        $orderingRepository = $this->getRepository(Ordering::class);
        foreach($this->getOrderedColumns($entity) as $column) {

            // dump($column);
            if(!str_ends_with($column, $property)) continue;

            list($className, $property) = explode("::", $column);
            $orderingRules = $orderingRepository->cacheByEntityIdAndEntityClass($entity->getId(), $className)->getResult();

            foreach($orderingRules as $ordering) {
                
                $data = $ordering->getEntityData();
                foreach($data as $column => $orderedIndexes) {
                    
                    try { $entityValue = $propertyAccessor->getValue($entity, $column); }
                    catch (Exception $e) { continue; }
                    
                    dump($column, $orderedIndexes, $entityValue->toArray());
                    if(is_array($entityValue)) {

                        $entityValue = array_map(fn($k) => $entityValue[$k], $orderedIndexes);
                        if($this->order == "DESC") $entityValue = array_reverse($entityValue);

                        $propertyAccessor->setValue($entity, $column, $entityValue);

                    } else if($entityValue instanceof PersistentCollection) {

                        $reflProp = new ReflectionProperty(PersistentCollection::class, "collection");
                        $reflProp->setAccessible(true);
                        dump($reflProp->getValue($entityValue));
                        $reflProp->setValue($entityValue, new OrderedArrayCollection($entityValue->unwrap()->toArray() ?? [], $this->order == "DESC" ? array_reverse($orderedIndexes) : $orderedIndexes));
                        dump($reflProp->getValue($entityValue));
                    }
                }
            }
        }
        // dump($entity);
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
                    
                    $type = $this->getClassMetadataManipulator()->getTypeOfField($entity, $property);
                    $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);

                    $value = $propertyAccessor->getValue($entity, $property);
                    if($doctrineType instanceof EnumType) {

                        $data[$property] = array_keys($doctrineType::getOrderingKeys($value));
                        if(!is_identity($data[$property]))
                            unset($data[$property]);

                    } else {

                        if($value instanceof Collection) $data[$property] = $value->toArray();
                        
                        $dataIdentifier = array_map(fn($e) => $e->getId(), $data[$property]);
                        uasort($dataIdentifier, 
                            fn($a,$b) => $a === null ? 1 : ($b === null ? -1 : ($a < $b ? -1 : 1))
                        );

                        $data[$property] = array_keys($dataIdentifier);
                        if(is_identity($data[$property]))
                            unset($data[$property]);
                    }
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

        // dump($entity);
        // exit(1);

        return isset($this->ordering[$className][$id]) ? [$this->ordering[$className][$id]] : [];
    }
}
