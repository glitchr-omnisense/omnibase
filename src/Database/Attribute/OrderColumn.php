<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Extension\ExtensionInlineInterface;
use Base\Database\Common\Collections\OrderedArrayCollection;
use Base\Database\Type\SetType;

use Doctrine\DBAL\Types\JsonType;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;
use Doctrine\DBAL\Types\StringType;
use Doctrine\ORM\PersistentCollection;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OrderColumn extends AbstractAttribute implements ExtensionInlineInterface
{
    public const ASC = "ASC";
    public const DESC = "DESC";

    public ?string $orderBy;
    public string $type;
    public function __construct(?string $orderBy = null, string $type = self::ASC)
    {  
        $this->orderBy = $orderBy;
        $this->type    = $type;
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

            // field/type checks
            $type = $this->getClassMetadataManipulator()->getTypeOfField($object, $targetValue);
            $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);

            $isSet    = is_instanceof($doctrineType, SetType::class);
            $isToMany = $this->getClassMetadataManipulator()->isToManySide($object, $targetValue);

            if (!$isSet && !$isToMany) {
                return false;
            }

            // Disallow using both @OrderColumn and @OrderBy?
            $siblingAttributes = $this->getAttributeReader()->getPropertyAttributes($object->getName(), OrderBy::class);
            if (array_key_exists($targetValue, $siblingAttributes)) {
                throw new Exception(
                    "@OrderBy metadata conflicts with @OrderColumn for \"" 
                    . $object->getName() . "::$targetValue\""
                );
            }
        }

        return ($target === AttributeReader::TARGET_PROPERTY);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        if (!$this->supports($target, $targetValue, $classMetadata)) return;

        // For associations/set, orderBy is mandatory and must be JSON or string
        $type = $this->getClassMetadataManipulator()->getTypeOfField($classMetadata, $targetValue);
        $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);
        $isSet    = is_instanceof($doctrineType, SetType::class);
        $isToMany = $this->getClassMetadataManipulator()->isToManySide($classMetadata, $targetValue);
    
        if ($isSet || $isToMany) {

            if (!$this->orderBy) {
                throw new Exception("The '".$classMetadata->name."::orderBy' field is mandatory for association '{$targetValue}'.");
            }

            $orderByType = $this->getClassMetadataManipulator()->getTypeOfField($classMetadata, $this->orderBy);
            if(!$orderByType) $orderByType = "json";

            // Check if orderBy is mapped as JSON or string
            $doctrineOrderByType = $this->getClassMetadataManipulator()->getDoctrineType($orderByType);
            if($doctrineOrderByType === null) return;

            $isJson = is_instanceof($doctrineOrderByType, JsonType::class) || $doctrineOrderByType === 'json' || $doctrineOrderByType === JsonType::class;
            $isString = is_instanceof($doctrineOrderByType, StringType::class) || $doctrineOrderByType === 'string' || $doctrineOrderByType === StringType::class;
            if (!$isJson && !$isString) {
                throw new Exception("The '".$classMetadata->name."::orderBy' field '{$this->orderBy}' must be of type JSON or string for association '{$targetValue}'.");
            }

            // Map the orderBy column if not already mapped
            if (!$classMetadata->hasField($this->orderBy)) {
                $classMetadata->mapField([
                    'fieldName' => $this->orderBy,
                    'type' => $orderByType,
                    'nullable' => true
                ]);
            }
        }
    }
    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        // For associations/set, orderBy is mandatory and must be JSON or string
        $type = $this->getClassMetadataManipulator()->getTypeOfField($classMetadata, $property);
        $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);
        $isSet    = is_instanceof($doctrineType, SetType::class);
        $isToMany = $this->getClassMetadataManipulator()->isToManySide($classMetadata, $property);
        if (!$isSet && !$isToMany) {
            return;
        }

        $property = $this->getClassMetadataManipulator()->getFieldName($entity, $property) ?? $property;
        $reflProp = new ReflectionProperty($classMetadata->name, $property);
        $reflProp->setAccessible(true);
        if (!$reflProp->isInitialized($entity)) {
            return; // skip if the property is not initialized
        }

        if($isToMany) $orderBy = $reflProp->getValue($entity)->map(fn($o) => $o->getId())->toArray();
        else $orderBy = array_keys($doctrineType->getOrderingKeys($reflProp->getValue($entity)));

        $orderBy = $this->type == "DESC" ? array_reverse($orderBy) : $orderBy;
        object_hydrate($entity, [$this->orderBy => json_encode($orderBy)]);
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null): void
    {
        // For associations/set, orderBy is mandatory and must be JSON or string
        $type = $this->getClassMetadataManipulator()->getTypeOfField($classMetadata, $property);
        $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);
        $isSet    = is_instanceof($doctrineType, SetType::class);
        $isToMany = $this->getClassMetadataManipulator()->isToManySide($classMetadata, $property);
        if (!$isSet && !$isToMany) {
            return;
        }

        $property = $this->getClassMetadataManipulator()->getFieldName($entity, $property) ?? $property;
        $reflProp = new ReflectionProperty($classMetadata->name, $property);
        $reflProp->setAccessible(true);
        if (!$reflProp->isInitialized($entity)) {
            return; // skip if the property is not initialized
        }

        if($isToMany) $orderBy = $reflProp->getValue($entity)->map(fn($o) => $o->getId())->toArray();
        else $orderBy = array_keys($doctrineType->getOrderingKeys($reflProp->getValue($entity)));

        if(\is_identity($orderBy)) $orderBy = []; // avoid serializing large arrays of integers

        $orderBy = $this->type == "DESC" ? array_reverse($orderBy) : $orderBy;
        object_hydrate($entity, [$this->orderBy => json_encode($orderBy)]);

        $this->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null): void
    {
         // For associations/set, orderBy is mandatory and must be JSON or string
        $type = $this->getClassMetadataManipulator()->getTypeOfField($classMetadata, $property);
        $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);
        $isSet    = is_instanceof($doctrineType, SetType::class);
        $isToMany = $this->getClassMetadataManipulator()->isToManySide($classMetadata, $property);
        if (!$isSet && !$isToMany) {
            return;
        }

        $property = $this->getClassMetadataManipulator()->getFieldName($entity, $property) ?? $property;
        try { $entityValue = $classMetadata->getFieldValue($entity, $property) ?? []; }
        catch (Exception $e) { return; }
        
        $reflProp = new ReflectionProperty($classMetadata->name, $this->orderBy);
        $reflProp->setAccessible(true);

        $orderingValue = $reflProp->getValue($entity);
        try {

            if (is_string($orderingValue)) {
                // throw exception on invalid JSON
                $decoded = json_decode($orderingValue, true, 512, JSON_THROW_ON_ERROR);
                $orderBy = is_array($decoded) ? $decoded : [];
            } else {
                $orderBy = [];
            }

        } catch (\JsonException $e) {
            // fallback on parse error
            $orderBy = [];
        }
        
        $orderBy = $this->type == "DESC" ? array_reverse($orderBy) : $orderBy;
        if ($entityValue instanceof PersistentCollection && $entityValue->getOwner() == $entity) {

            $reflProp = new ReflectionProperty(PersistentCollection::class, "collection");
            $reflProp->setAccessible(true);
            $reflProp->setValue($entityValue, new OrderedArrayCollection($entityValue ?? [], $orderBy));

        } else {

            $entityValue = array_values(usort_key($entityValue, $orderBy));
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $propertyAccessor->setValue($entity, $property, $entityValue);
            object_hydrate($entity, [$this->orderBy => json_encode([])]);
        }

    }
}