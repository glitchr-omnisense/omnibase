<?php

namespace Base\Database\Factory;

use ArgumentCountError;
use Base\Database\Factory\AggregateHydrator\PopulableInterface;
use Base\Database\Factory\AggregateHydrator\SerializableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManagerInterface;

use Exception;
use ReflectionClass;
use ReflectionObject;
use Symfony\Component\PropertyAccess\PropertyAccess;

class EntityHydrator
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    /**
     * @var array
     */
    protected $reflProperties = [];

    const HYDRATE_BY_FIELD  = 1; // The keys in the data array are entity field names
    const HYDRATE_BY_COLUMN = 2; // The keys in the data array are database column names

    /**
     * If true, then associations are filled only with reference proxies. This is faster than querying them from
     * database, but if the associated entity does not really exist, it will cause:
     * * The insert/update to fail, if there is a foreign key defined in database
     * * The record ind database also pointing to a non-existing record
     *
     * @var bool
     */
    protected $hydrateAssociationReferences = true;

    /**
     * Aggregate methods: by default, it is "object properties" by "deep copy" method without fallback
     */
    const DEFAULT_AGGREGATE  = 0;
    const CLASS_METHODS      = 1;
    const OBJECT_PROPERTIES  = 2;
    const ARRAY_OBJECT       = 4;
    const DEEPCOPY           = 8;
    const CONSTRUCT          = 16;

    public function __construct(EntityManagerInterface $entityManager, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor(); 
    }

    public function hydrate(mixed $entity, null|object|array $data = [], array $dataExceptions = [], int $aggregateModel = self::DEFAULT_AGGREGATE, ...$constructArguments): mixed
    {
        $reflClass = new ReflectionClass($entity);
        if (is_string($entity) && class_exists($entity))
            $entity = ($aggregateModel & self::CONSTRUCT) ? new $entity(...$constructArguments) : $reflClass->newInstanceWithoutConstructor();
        if (!is_object($entity))
            throw new Exception('Entity passed to EntityHydrator::hydrate() must be a class name or entity object');

        $data = array_filter($this->toArray($data), fn($e) => $e !== null);
        $data = array_key_removes($data, ...$dataExceptions);

        if(!$this->hydrateByArrayObject($entity, $data, $aggregateModel)) {
            $this->hydrateProperties($entity, $data, $aggregateModel);
            $this->hydrateAssociations($entity, $data, $aggregateModel);
        }

        return $entity;
    }

    public function fromProxy(Proxy $proxy)
    {
        $entity = str_strip(get_class($proxy), "Proxies\\__CG__\\");
        $classMetadata   = $this->entityManager->getClassMetadata($entity);

        $this->entityManager->detach($proxy);
        return $this->entityManager->find($entity, $this->getPropertyValue($proxy, begin($classMetadata->identifier)));
    }

    public function toArray(object|array $objectOrArray, bool $deepcast = false): array
    {
        $array = [];

        if($objectOrArray instanceof Collection) $objectOrArray = $objectOrArray->toArray();
        if(is_array($objectOrArray)) {

            $array = $objectOrArray;
            if($deepcast) {

                foreach($array as $propertyName => $value)
                    $array[$propertyName] = is_object($value) ? $this->toArray($value, $deepcast) : $value;
            }

        } else if(is_object($objectOrArray)) {

            $reflectionClass = new \ReflectionClass(get_class($objectOrArray));
            foreach ($reflectionClass->getProperties() as $reflProperty) {

                $reflProperty->setAccessible(true);
                
                $value = $reflProperty->getValue($objectOrArray);
                
                if (is_object($value) && $deepcast)
                    $value = $this->toArray($value, $deepcast);

                $array[$reflProperty->getName()] = $value;
            }
        }

        return $array;
    }

    public function clone(object $entity, int $aggregateModel = self::DEEPCOPY) 
    { 
        return $this->hydrate(get_class($entity), $entity, [], $aggregateModel);
    }

    public function setHydrateAssociationReferences(bool $hydrateAssociationReferences) 
    { 
        $this->hydrateAssociationReferences = $hydrateAssociationReferences; 
        return $this;
    }

    protected function hydrateId(object $entity, object $data): object
    {
        $reflEntity = new ReflectionObject($entity);
        $metaEntity = $this->entityManager->getClassMetadata(get_class($entity));
        
        $reflData   = new ReflectionObject($data);
        $metaData   = $this->entityManager->getClassMetadata(get_class($data));

        foreach($metaData->identifier as $id) {

            if (in_array($id, $metaEntity->identifier))
                $this->setPropertyValue($entity, $id, $this->getPropertyValue($data, $id, $reflData), $reflEntity);
        }

        return $entity;
    }

    protected function hydrateByArrayObject(object $entity, array $data, int $aggregateModel): bool
    {
        // Hydrate by ArrayObject
        if($aggregateModel & self::ARRAY_OBJECT) {

            if(class_implements_interface(PopulableInterface::class, $entity)) {
                $entity->populate($data);
                return true;
            } else if(class_implements_interface(SerializableInterface::class, $entity)) {
                $entity->exchangeArray($data);
                return true;
            }
        }

        return false;
    }

    protected function hydrateProperties(object $entity, array $data, int $aggregateModel): self
    {
        $reflEntity = new ReflectionObject($entity);

        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));
        foreach ($classMetadata->fieldMappings as $fieldName => $fieldMapping) {

            if($this->getPropertyValue($entity, $fieldName) !== null)
                continue;

            $type = $this->classMetadataManipulator->getTypeOfField($entity, $fieldName);
            $this->setPropertyValue($entity, $fieldName, null, new ReflectionObject($entity));
        }

        foreach ($data as $propertyName => $value) {

            if($this->classMetadataManipulator->hasAssociation($entity, $propertyName))
                continue;

            if($aggregateModel & self::CLASS_METHODS && $this->propertyAccessor->isWritable($entity, $propertyName)) {

                $this->propertyAccessor->setValue($entity, $propertyName, $value);

            } else if($aggregateModel & self::OBJECT_PROPERTIES || !($aggregateModel & self::CLASS_METHODS)) {

                $reflProperty = $reflEntity->hasProperty($propertyName) ? $reflEntity->getProperty($propertyName) : null;
                if($reflProperty !== null) {

                    $propertyName = $reflProperty->getName();
                    $this->setPropertyValue($entity, $propertyName, $reflProperty->getType() == "array" ? [] : null);

                    if (!in_array($propertyName, $classMetadata->identifier, true))
                        $this->setPropertyValue($entity, $propertyName, $data[$propertyName], $reflEntity);
                }
            }
        }

        return $this;
    }

    protected function hydrateAssociations(mixed $entity, array $data, int $aggregateModel): self
    {
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));
        foreach ($classMetadata->associationMappings as $fieldName => $associationMapping) {

            if($this->getPropertyValue($entity, $fieldName) !== null)
                continue;

            if(!$this->classMetadataManipulator->isToManySide($entity, $fieldName))
                continue;

            $this->setPropertyValue($entity, $fieldName, new ArrayCollection(), new ReflectionObject($entity));
        }

        foreach ($data as $propertyName => $value) {

            if(!$this->classMetadataManipulator->hasAssociation($entity, $propertyName))
                return $this;

            $associationMapping = $classMetadata->associationMappings[$classMetadata->getFieldName($propertyName)];

            if ($this->classMetadataManipulator->isToOneSide($entity, $propertyName))
                $this->hydrateToOneAssociation($entity, $propertyName, $associationMapping, $value, $aggregateModel);

            if ($this->classMetadataManipulator->isToManySide($entity, $propertyName))
                $this->hydrateToManyAssociation($entity, $propertyName, $associationMapping, $value, $aggregateModel);
        }

        return $this;
    }

    protected function hydrateToOneAssociation(mixed $entity, string $propertyName, array $mapping, mixed $value, int $aggregateModel): self
    {
        if(!($aggregateModel & self::DEEPCOPY)) $association = $value;
        else $association = $this->findAssociation($mapping['targetEntity'], $value);

        if($aggregateModel & self::CLASS_METHODS && $this->propertyAccessor->isWritable($entity, $propertyName)) {

            if(is_array($association))
                $association = $this->hydrate($mapping['targetEntity'], $association, [], $aggregateModel);

            $this->propertyAccessor->setValue($entity, $propertyName, $association);

        } else if($aggregateModel & self::OBJECT_PROPERTIES || !($aggregateModel & self::CLASS_METHODS)) {

            $this->setPropertyValue($entity, $propertyName, $association, new ReflectionObject($entity));
        }

        return $this;
    }

    protected function hydrateToManyAssociation(mixed $entity, string $propertyName, array $mapping, mixed $values, int $aggregateModel): self
    {
        if($values !== null && !is_object($values) && !is_array($values)) 
            throw new Exception("Failed to turn \"$values\" into an association in \"".get_class($entity)."\". Did you pass an object?");

        $values = new ArrayCollection(is_array($values) ? $values : [$values]);

        $associations = [];
        foreach ($values as $key => $value) {

            if($value instanceof Collection) {

                $entityValue = $this->getPropertyValue($entity, $propertyName)->toArray();
                $associations = new ArrayCollection($entityValue + $value->toArray());

            } else if (is_array($value)) {

                $associations[$key] = $this->hydrate($mapping['targetEntity'], $value, [], $aggregateModel);

            } elseif ($association = $this->findAssociation($mapping['targetEntity'], $value)) {
            
                $associations[$key] = $association;
            }
        }

        if(!$associations instanceof ArrayCollection)
            $associations = new ArrayCollection($associations);

        $this->setPropertyValue($entity, $propertyName, $associations, new ReflectionObject($entity));

        return $this;
    }

    protected function getProperty(mixed $entity, string $propertyName, ?ReflectionObject $reflEntity = null): mixed { return $this->getProperties($entity, $reflEntity)[$propertyName] ?? null; }
    protected function getProperties(mixed $entity, ?ReflectionObject $reflEntity = null): array {

        $reflEntity = $reflEntity === null ? new ReflectionObject($entity) : $reflEntity;
        if(array_key_exists($reflEntity->getName(), $this->reflProperties))
            return $this->reflProperties[$reflEntity->getName()];

        $this->reflProperties[$reflEntity->getName()] = [];

        $classFamily = [];
        do {

            $classFamily[] = $reflEntity->getName();

            $this->reflProperties[$reflEntity->getName()] = [];
            foreach ($reflEntity->getProperties() as $reflProperty) {
                
                $reflProperty->setAccessible(true);
                $this->reflProperties[$reflEntity->getName()][$reflProperty->getName()] = $reflProperty;
            }

            foreach($classFamily as $className)
                $this->reflProperties[$className] = array_merge($this->reflProperties[$reflEntity->getName()], $this->reflProperties[$className]);

        } while ($reflEntity = $reflEntity->getParentClass());

        return $this->reflProperties[get_class($entity)];
    }

    protected function getPropertyValues(mixed $entity, ?ReflectionObject $reflEntity = null): array { return array_map(fn($rp) => $rp->getValue($entity), $this->getProperties($entity, $reflEntity)); }
    protected function getPropertyValue(mixed $entity, string $propertyName, ?ReflectionObject $reflEntity = null): mixed { return $this->getPropertyValues($entity, $reflEntity)[$propertyName] ?? null; }
    protected function setPropertyValue(mixed $entity, string $propertyName, $value, ?ReflectionObject $reflEntity = null): mixed
    {
        $reflEntity = $reflEntity === null ? new ReflectionObject($entity) : $reflEntity;

        $reflProperty = $this->getProperty($entity, $propertyName, $reflEntity);
        $reflProperty->setAccessible(true);
        $reflProperty->setValue($entity, $value);

        return $this;
    }

    public function fetchEntityName(string $entityName, array|string $propertyPath, ?array &$data = null): ?string { return $this->fetchEntityMapping($entityName, $propertyPath, $data)["targetEntity"] ?? null; }
    public function fetchEntityMapping(string $entityName, array|string $propertyPath): ?array
    {
        $propertyPath = is_array($propertyPath) ? $propertyPath : explode(".", $propertyPath);
        $propertyName = head($propertyPath);

        $classMetadata = $this->entityManager->getClassMetadata($entityName);
        if ($classMetadata->hasAssociation($classMetadata->getFieldName($propertyName)))
            $entityMapping = $classMetadata->associationMappings[$classMetadata->getFieldName($propertyName)];
        else if ($classMetadata->hasField($classMetadata->getFieldName($propertyName)))
            $entityMapping = $classMetadata->fieldMappings[$classMetadata->getFieldName($propertyName)];
        else return null;

        $propertyName = $propertyPath ? head($propertyPath) : $propertyName;
        $propertyPath = tail($propertyPath, $this->classMetadataManipulator->isToManySide($entityName, $propertyName) ? -2 : -1);
        if(!$propertyPath) return $entityMapping;

        return $this->fetchEntityMapping($entityMapping["targetEntity"], implode(".", $propertyPath));
    }

    protected function findAssociation($entityName, $identifier): mixed
    {
        if(is_object($identifier))
            $identifier = $this->propertyAccessor->isReadable($identifier, "id") 
                        ? $this->propertyAccessor->getValue($identifier, "id") : null;

        if(!$identifier) return null;

        if ($this->hydrateAssociationReferences && $identifier !== null) 
            return $this->entityManager->getReference($entityName, $identifier);

        return $this->entityManager->find($entityName, $identifier);
    }
}