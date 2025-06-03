<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Annotation\Extension\ExtensionInlineInterface;
use Base\Database\Common\Collections\OrderedArrayCollection;
use Base\Database\Type\SetType;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;
use Doctrine\DBAL\Types\StringType;
use Doctrine\ORM\PersistentCollection;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY"})
 */

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OrderColumn extends AbstractAnnotation implements ExtensionInlineInterface
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
            $isArray  = is_instanceof($doctrineType, JsonType::class);
            $isToMany = $this->getClassMetadataManipulator()->isToManySide($object, $targetValue);

            if (! $isSet && ! $isArray && ! $isToMany) {
                return false;
            }

            // conflict with @OrderBy?
            $siblingAnnotations = $this->getAnnotationReader()->getPropertyAnnotations($object->getName(), OrderBy::class);
            if (array_key_exists($targetValue, $siblingAnnotations)) {
                throw new Exception(
                    "@OrderBy metadata conflicts with @OrderColumn for \"" 
                    . $object->getName() . "::$targetValue\""
                );
            }
        }

        return ($target === AnnotationReader::TARGET_PROPERTY);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        if (!$this->supports($target, $targetValue, $classMetadata)) return;

        $type = $this->getClassMetadataManipulator()->getTypeOfField($classMetadata, $targetValue);
        $doctrineType = $this->getClassMetadataManipulator()->getDoctrineType($type);

        $isAssociation = $classMetadata->hasAssociation($targetValue);

        // For associations, orderBy is mandatory and must be JSON or string
        if ($isAssociation) {

            if (!$this->orderBy) {
                throw new Exception("The 'orderBy' field is mandatory for association '{$targetValue}'.");
            }

            $orderByType = $this->getClassMetadataManipulator()->getTypeOfField($classMetadata, $this->orderBy);

            // Check if orderBy is mapped as JSON or string
            $doctrineOrderByType = $this->getClassMetadataManipulator()->getDoctrineType($orderByType);
            if($doctrineOrderByType === null) return;

            $isJson = is_instanceof($doctrineOrderByType, JsonType::class) || $doctrineOrderByType === 'json' || $doctrineOrderByType === JsonType::class;
            $isString = is_instanceof($doctrineOrderByType, StringType::class) || $doctrineOrderByType === 'string' || $doctrineOrderByType === StringType::class;
            if (!$isJson && !$isString) {
                throw new Exception("The 'orderBy' field '{$this->orderBy}' must be of type JSON or string for association '{$targetValue}'.");
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
        // Only compute for associations
        if (!$classMetadata->hasAssociation($property)) {
            return;
        }

        $property = $this->getClassMetadataManipulator()->getFieldName($entity, $property) ?? $property;
        $reflProp = new ReflectionProperty($classMetadata->name, $property);
        $reflProp->setAccessible(true);
        if (!$reflProp->isInitialized($entity)) {
            return; // skip if the property is not initialized
        }

        $ids = $reflProp->getValue($entity)->map(fn($o) => $o->getId())->toArray();
        object_hydrate($entity, [$this->orderBy => $ids]);
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null): void
    {
        // Only compute for associations
        if (!$classMetadata->hasAssociation($property)) {
            return;
        }

        $property = $this->getClassMetadataManipulator()->getFieldName($entity, $property) ?? $property;
        $reflProp = new ReflectionProperty($classMetadata->name, $property);
        $reflProp->setAccessible(true);
        if (!$reflProp->isInitialized($entity)) {
            return; // skip if the property is not initialized
        }

        $ids = $reflProp->getValue($entity)->map(fn($o) => $o->getId())->toArray();
        object_hydrate($entity, [$this->orderBy => json_encode($ids)]);

        $this->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null): void
    {
        // Only compute for associations
        if (!$classMetadata->hasAssociation($property)) {
            return;
        }

        $property = $this->getClassMetadataManipulator()->getFieldName($entity, $property) ?? $property;
        try { $entityValue = $classMetadata->getFieldValue($entity, $property) ?? []; }
        catch (Exception $e) { return; }
        
        $reflProp = new ReflectionProperty($classMetadata->name, $this->orderBy);
        $reflProp->setAccessible(true);

        $value = $reflProp->getValue($entity);
        try {

            if (is_string($value)) {
                // throw exception on invalid JSON
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                $ids = is_array($decoded) ? $decoded : [];
            } else {
                $ids = [];
            }

        } catch (\JsonException $e) {
            // fallback on parse error
            $ids = [];
        }

        $ids = $this->type == "DESC" ? array_reverse($ids) : $ids;
        if (is_array($entityValue)) {
            
            $entityValue = array_flip(array_transforms(fn($k, $v): array => [$entityValue[$k], $v], $ids));
            ksort($entityValue);

            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $propertyAccessor->setValue($entity, $property, $entityValue);

        } elseif ($entityValue instanceof PersistentCollection && $entityValue->getOwner() == $entity) {

            $reflProp = new ReflectionProperty(PersistentCollection::class, "collection");
            $reflProp->setAccessible(true);

            $reflProp->setValue($entityValue, new OrderedArrayCollection($entityValue ?? [], $ids));
        }
    }
}