<?php

namespace Base\Database\Annotation;

use App\Entity\User\Merchant;
use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Common\Collections\OrderedArrayCollection;
use Base\Database\Factory\EntityExtensionInterface;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Base\Entity\Extension\Ordering;
use Base\Enum\EntityAction;
use Base\Traits\BaseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
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
    use BaseTrait;

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
            
            $isEnum = is_a($doctrineType, EnumType::class);
            $isToMany = $this->getClassMetadataManipulator()->isToManySide($object, $targetValue);

            if(!$isEnum && !$isToMany)
                throw new \Exception("Unexpected column type found for @OrderColumn \"".$targetValue."\" found in \"".$object->getName()."\": expecting \"Collection\" or \"array\"");
                
            $siblingAnnotations = $this->getAnnotationReader()->getDefaultPropertyAnnotations($object->getName(), OrderBy::class);
            if(array_key_exists($targetValue, $siblingAnnotations))
                throw new \Exception("@OrderBy annotation is in conflict with @OrderColum for \"".$object->getName()."::$targetValue\"");
        }
        
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public static $orderedColumns   = [];
    public static function get():array { return self::$orderedColumns; }
    public static function has($entity, $property):bool { return isset(self::$orderedColumns[$entity]) && in_array($property, self::$orderedColumns[$entity]); } 
    
    public function getOrderedColumns($entity)
    {
        $orderedColumns = [];
        foreach(self::$orderedColumns as $column) {

            list($className, $_) = explode("::", $column);
            if(is_a($entity, $className)) 
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

            if(!str_ends_with($column, $property)) continue;

            list($className, $property) = explode("::", $column);
            $orderingRules = $orderingRepository->cacheByEntityIdAndEntityClass($entity->getId(), $className)->getResult();

            foreach($orderingRules as $ordering) {
                
                $data = $ordering->getEntityData();

                foreach($data as $column => $orderedIndexes) {
                    
                    try { $entityValue = $propertyAccessor->getValue($entity, $column); }
                    catch (Exception $e) { continue; }

                    if(is_array($entityValue)) {

                        $entityValue = array_map(fn($k) => $entityValue[$k], $orderedIndexes);
                        $propertyAccessor->setValue($entity, $column, $entityValue);

                    } else if($entityValue instanceof PersistentCollection) {

                        $reflProp = new ReflectionProperty(PersistentCollection::class, "collection");
                        $reflProp->setAccessible(true);
                        $reflProp->setValue($entityValue, new OrderedArrayCollection($entityValue->unwrap()->toArray() ?? [], $orderedIndexes));
                    }
                }
            }
        }
    }

    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        $orderingRepository = $this->getEntityManager()->getRepository(Ordering::class);
        $ordering = $orderingRepository->findOneByEntityIdAndEntityClass($entity->getId(), $className);
        
        switch($action) {

            case EntityAction::INSERT:
            case EntityAction::UPDATE:

                $propertyAccessor = PropertyAccess::createPropertyAccessor();

                $data = [];
                $oldData = [];
                foreach($properties as $property) {
                    
                    $type = $this->getClassMetadataManipulator()->getTypeOfField($entity, $property);
                    $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);

                    $value = $propertyAccessor->getValue($entity, $property);
                    if($doctrineType instanceof EnumType) {
                    
                        $data[$property] = array_keys($doctrineType::getOrderingKeys($value));

                    } else {

                        if($value instanceof Collection) $data[$property] = $value->toArray();
                        
                        $dataIdentifier = array_map(fn($e) => $e->getId(), $data[$property]);
                        uasort($dataIdentifier, 
                            fn($a,$b) => $a === null ? 1 : ($b === null ? -1 : ($a < $b ? -1 : 1))
                        );

                        $data[$property] = array_keys($dataIdentifier);
                    }
                }

                $ordering = $ordering ?? new Ordering();
                $ordering->setEntityData($data);

                break;

            case EntityAction::DELETE:
                break;

            default:
                throw new Exception("Unknown action \"$action\" passed to ".__CLASS__);
        }

        return $ordering ? [$ordering] : [];
    }
}
