<?php

namespace Base\Database\Factory;

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
     * Tells whether the input data array keys are entity field names or database column names
     *
     * @var int one of EntityHydrator::HYDRATE_BY_* constants
     */
    protected $hydrateBy = self::HYDRATE_BY_FIELD;

    /**
     * Aggregate methods: by default, it is "object properties" by "deep copy" method without fallback
     */
    const DEFAULT_AGGREGATE  = 0;
    const CLASS_METHODS      = 1;
    const OBJECT_PROPERTIES  = 2;
    const ARRAY_SERIALIZABLE = 4;
    const DEEPCOPY           = 8;
    const FALLBACKS          = 16;
    const CONSTRUCT          = 32;

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

        $this->hydrateProperties($entity, $data, $aggregateModel);
        $this->hydrateAssociations($entity, $data, $aggregateModel);

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

    public function setHydrateBy(int $hydrateBy)
    {
        $this->hydrateBy = $hydrateBy;
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

    protected function hydrateProperties(object $entity, array $data, int $aggregateModel): self
    {
        $reflEntity = new ReflectionObject($entity);

        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));
        $skipFields = $classMetadata->identifier;

        $useFallbacks = $aggregateModel & self::FALLBACKS;

        foreach ($data as $field => $value) {

            if($this->classMetadataManipulator->hasAssociation($entity, $field))
                continue;

            $gotHydrated = false;
            $defaultAggregate = !($aggregateModel & self::ARRAY_SERIALIZABLE) && !($aggregateModel & self::CLASS_METHODS) && !($aggregateModel & self::OBJECT_PROPERTIES); 

            if($aggregateModel & self::ARRAY_SERIALIZABLE && !$gotHydrated) {

                if(class_implements_interface(PopulableInterface::class, $entity)) {

                    $entity->populate($data);
                    $gotHydrated = true;

                } else if(class_implements_interface(SerializableInterface::class, $entity)) {

                    $entity->exchangeArray($data);
                    $gotHydrated = true;

                } else {

                    throw new Exception("\"Failed to use ARRAY_SERIALIZABLE option to aggregate data into ".get_class($entity)."\". This class doesn't implements neither \"".PopulableInterface::class."\" nor \"".SerializableInterface::class."\"");
                }

                if(!$gotHydrated && !$useFallbacks) continue;
            }
            
            if(($defaultAggregate || $aggregateModel & self::OBJECT_PROPERTIES) && !$gotHydrated) {

                $reflProperty = $reflEntity->hasProperty($field) ? $reflEntity->getProperty($field) : null;
                if($reflProperty !== null) {

                    $propertyName = $reflProperty->getName();
                    $this->setPropertyValue($entity, $propertyName, $reflProperty->getType() == "array" ? [] : null);

                    $dataKey = $this->hydrateBy === self::HYDRATE_BY_FIELD ? $propertyName : $classMetadata->getColumnName($propertyName);
                    if (array_key_exists($dataKey, $data) && !in_array($propertyName, $skipFields, true))
                        $this->setPropertyValue($entity, $propertyName, $data[$dataKey], $reflEntity);

                    $gotHydrated = true;
                }

                if(!$useFallbacks) continue;
            }

            if($aggregateModel & self::CLASS_METHODS && !$gotHydrated) {

                if($this->propertyAccessor->isWritable($entity, $field)) {
                    
                    $this->propertyAccessor->setValue($entity, $field, $data[$field]);
                    $gotHydrated = true;
                }

                if(!$gotHydrated && !$useFallbacks) continue;
            }
        }

        return $this;
    }

    protected function hydrateAssociations(mixed $entity, array $data, int $aggregateModel): self
    {
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));
        foreach ($classMetadata->associationMappings as $association => $mapping) {

            $associationData = $this->getAssociatedId($association, $mapping, $data);
            if (!empty($associationData)) {

                if ($this->classMetadataManipulator->isToOneSide($entity, $association))
                    $this->hydrateToOneAssociation($entity, $association, $mapping, $associationData, $aggregateModel);

                if ($this->classMetadataManipulator->isToManySide($entity, $association)) {

                    if($classMetadata->getFieldName($association) === $association && $aggregateModel & self::DEEPCOPY)
                        $this->setPropertyValue($entity, $association, new ArrayCollection());
                    
                    $this->hydrateToManyAssociation($entity, $association, $mapping, $associationData, $aggregateModel);
                }
            }
        }

        return $this;
    }

    protected function getAssociatedId(string $column, array $mapping, array $data): mixed
    {
        if ($this->hydrateBy === self::HYDRATE_BY_FIELD)
            return isset($data[$column]) ? $data[$column] : null;

        // from this point it is self::HYDRATE_BY_COLUMN
        // we do not support compound foreign keys (yet)
        if (isset($mapping['joinColumns']) && count($mapping['joinColumns']) === 1) {

            $columnName = $mapping['joinColumns'][0]['name'];
            return isset($data[$columnName]) ? $data[$columnName] : null;
        }

        // If joinColumns does not exist, then this is not the owning side of an association
        // This should not happen with column based hydration
        return null;
    }

    protected function hydrateToOneAssociation(mixed $entity, string $propertyName, array $mapping, mixed $value, int $aggregateModel): self
    {
        $reflEntity = new ReflectionObject($entity);

        if(!($aggregateModel & self::DEEPCOPY)) $associationObject = $value;
        else $associationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value);

        if ($associationObject !== null)
            $this->setPropertyValue($entity, $propertyName, $associationObject, $reflEntity);

        return $this;
    }

    protected function hydrateToManyAssociation(mixed $entity, string $propertyName, array $mapping, mixed $value, int $aggregateModel): self
    {
        $reflEntity = new ReflectionObject($entity);
        if($value !== null && !is_object($value) && !is_array($value)) 
            throw new Exception("Failed to turn \"$value\" into an association in \"".get_class($entity)."\". Did you pass an object?");

        $values = is_array($value) ? $value : [$value];
        
        if(!($aggregateModel & self::DEEPCOPY)) $associationObjects = $value;
        else {

            $associationObjects = [];
            foreach ($values as $key => $value) {

                if($value instanceof Collection) {

                    $entityValue = $this->getPropertyValue($entity, $propertyName)->toArray();
                    $associationObjects = new ArrayCollection($entityValue + $value->toArray());

                } else if (is_array($value)) {

                    $associationObjects[$key] = $this->hydrate($mapping['targetEntity'], $value);

                } elseif ($associationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value)) {
                
                    $associationObjects[$key] = $associationObject;
                }
            }
        }

        if(!$associationObjects instanceof ArrayCollection)
            $associationObjects = new ArrayCollection($associationObjects);

        $this->setPropertyValue($entity, $propertyName, $associationObjects, $reflEntity);
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

    protected function fetchAssociationEntity($entityName, $identifier): mixed
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