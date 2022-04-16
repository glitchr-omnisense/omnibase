<?php

namespace Base\Database\Factory;

use Base\Database\Factory\AggregateHydrator\PopulableInterface;
use Base\Database\Factory\AggregateHydrator\SerializableInterface;
use Base\Database\Type\SetType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\DBAL\Types\ArrayType;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use ReflectionObject;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

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
    const IGNORE_NULLS       = 32;

    public function __construct(EntityManagerInterface $entityManager, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor(); 
        $this->serializer = new Serializer([new ObjectNormalizer()]);
    }

    public function hydrate(mixed $entity, null|array|object $data = [], array $fieldExceptions = [], int $aggregateModel = self::DEFAULT_AGGREGATE, ...$constructArguments): mixed
    {
        $reflClass = new ReflectionClass($entity);
        if (is_string($entity) && class_exists($entity))
            $entity = ($aggregateModel & self::CONSTRUCT) ? new $entity(...$constructArguments) : $reflClass->newInstanceWithoutConstructor();
        if (!is_object($entity))
            throw new Exception('Entity passed to EntityHydrator::hydrate() must be a class name or entity object');

        $this->setDefaults($entity);
        $data = $this->dehydrate($data, $fieldExceptions);

        if(!$this->hydrateByArrayObject($entity, $data, $aggregateModel)) {
            $this->hydrateProperties($entity, $data, $aggregateModel);
            $this->hydrateAssociations($entity, $data, $aggregateModel);
        }

        $this->bindAliases($entity);
        return $entity;
    }

    public function dehydrate(mixed $entity, array $fieldExceptions = []) {
    
        $data = $entity ?? [];
        $data = $data instanceof Collection ? $data->toArray() : $data;
        if(is_object($data)) $data = array_filter(array_transforms(
            fn($k, $e):array => [str_lstrip($k, "\x00*\x00"), $e instanceof ArrayCollection && $e->isEmpty() ? null : $e], 
            (array) $data
        ));

        return array_key_removes($data, ...$fieldExceptions);
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

    protected function bindAliases(object $entity): self
    {
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));
        foreach ($classMetadata->fieldNames as $alias => $column) {
        
            $fn = function() use ($alias, $column) {

                $aliasValue  = $this->$alias;
    
                $columnValue = $this->$column;
                if ($aliasValue instanceof ArrayCollection && $columnValue instanceof ArrayCollection)
                    $aliasValue = new ArrayCollection($columnValue->toArray() + $aliasValue->toArray()); 
                else if($columnValue !== null)
                    $aliasValue = $columnValue;

                $this->$alias = &$this->$column; // Bind variable together..
                $this->$alias = $aliasValue;
    
                return $this;
            };
    
            $fnClosure = \Closure::bind($fn, $entity, get_class($entity));
            $fnClosure();
        }

        return $this;
    }
    
    protected function setDefaults(object $entity)
    {
        $reflEntity = new ReflectionObject($entity);
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));

        foreach ($classMetadata->fieldMappings as $fieldName => $fieldMapping) {

            if ($this->getPropertyValue($entity, $fieldName) !== null)
                continue;

            $doctrineType = $this->classMetadataManipulator->getDoctrineType($fieldMapping["type"]);
            if(is_instanceof($doctrineType, ArrayType::class) || is_instanceof($doctrineType, SetType::class))
                $this->setPropertyValue($entity, $fieldName, [], $reflEntity);
        }

        foreach ($classMetadata->associationMappings as $fieldName => $associationMapping) {

            if ($this->getPropertyValue($entity, $fieldName) !== null)
                continue;

            if ($this->classMetadataManipulator->isToManySide($entity, $fieldName))
                $this->setPropertyValue($entity, $fieldName, new ArrayCollection(), $reflEntity);
        }

    }

    protected function hydrateProperties(object $entity, array $data, int $aggregateModel): self
    {
        $reflEntity = new ReflectionObject($entity);
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));

        foreach ($data as $propertyName => $value) {

            if($this->classMetadataManipulator->hasAssociation($entity, $propertyName))
                continue;

            if ($value === null && $aggregateModel & self::IGNORE_NULLS)
                continue;

            $aggregateFallback = !($aggregateModel & self::CLASS_METHODS);

            if($aggregateModel & self::CLASS_METHODS && $this->propertyAccessor->isWritable($entity, $propertyName)) {

                $this->propertyAccessor->setValue($entity, $propertyName, $value);

            } else if($aggregateModel & self::OBJECT_PROPERTIES || $aggregateFallback) {

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
        foreach ($data as $propertyName => $value) {

            if(!$this->classMetadataManipulator->hasAssociation($entity, $propertyName))
                continue;

            if ($data === null && $aggregateModel & self::IGNORE_NULLS)
                continue;

            $associationMapping = $classMetadata->associationMappings[$classMetadata->getFieldName($propertyName)];
            if ($this->classMetadataManipulator->isToOneSide($entity, $propertyName))
                $this->hydrateAssociationToOne($entity, $propertyName, $associationMapping, $value, $aggregateModel);

            if ($this->classMetadataManipulator->isToManySide($entity, $propertyName))
                $this->hydrateAssociationToMany($entity, $propertyName, $associationMapping, $value, $aggregateModel);
        }

        return $this;
    }

    protected function hydrateAssociationToOne(mixed $entity, string $propertyName, array $mapping, mixed $value, int $aggregateModel): self
    {
        if(is_array($value)) $association = $this->hydrate($mapping['targetEntity'], $value, [], $aggregateModel);
        else if(!is_object($value)) $association = $this->findAssociation($mapping['targetEntity'], $value);
        else $association = $value;

        if($aggregateModel & self::DEEPCOPY) $association = clone $association;
        $this->setPropertyValue($entity, $propertyName, $association, new ReflectionObject($entity));

        return $this;
    }

    protected function hydrateAssociationToMany(mixed $entity, string $propertyName, array $mapping, mixed $values, int $aggregateModel): self
    {
        if($values !== null && !is_object($values) && !is_array($values)) 
            throw new Exception("Failed to turn \"$values\" into an association in \"".get_class($entity)."\". Did you pass an object?");

        $reflEntity = new ReflectionObject($entity);
        $association = $values instanceof Collection ? $values : new ArrayCollection($values);
        if($association instanceof ArrayCollection) {

            foreach ($association as $key => $value) {

                if (is_array($value)) $association[$key] = $this->hydrate($mapping['targetEntity'], $value, [], $aggregateModel);
                else if ($targetEntity = $this->findAssociation($mapping['targetEntity'], $value))
                    $association[$key] = $targetEntity;
            }
        }

        if($aggregateModel & self::DEEPCOPY) $association = clone $association;
        $this->setPropertyValue($entity, $propertyName, $association, $reflEntity);

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