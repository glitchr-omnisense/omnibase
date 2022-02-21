<?php

namespace Base\Database\Factory;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManagerInterface;

use Exception;
use ReflectionClass;
use ReflectionObject;
use Symfony\Component\PropertyAccess\PropertyAccess;

// Credits: https://github.com/pmill/doctrine-array-hydrator

class EntityHydrator
{
    /**
     * The keys in the data array are entity field names
     */
    const HYDRATE_BY_FIELD = 1;

    /**
     * The keys in the data array are database column names
     */
    const HYDRATE_BY_COLUMN = 2;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

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
     * @var array
     */
    protected $reflProperties = [];

    public function __construct(EntityManagerInterface $entityManager, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor(); 
    }

    public function hydrate(mixed $entity, null|object|array $data, array $reflPropertyExceptions = [], bool $deepcopy = true): mixed
    {
        $reflClass = new ReflectionClass($entity);
        if (is_string($entity) && class_exists($entity))
            $entity = $reflClass->newInstanceWithoutConstructor();
        if (!is_object($entity))
            throw new Exception('Entity passed to EntityHydrator::hydrate() must be a class name or entity object');

        $data = array_filter($this->toArray($data), fn($e) => $e !== null);
        $data = array_key_removes($data, ...$reflPropertyExceptions);

        $this->hydrateProperties($entity, $data);
        $this->hydrateAssociations($entity, $data, $deepcopy);

        return $entity;
    }

    /**
     * This method returns the array corresponding to an object, including non public members.
     *
     * If the deep flag is true, is will operate recursively, otherwise (if false) just at the first level.
     *
     * @param object $obj
     * @param bool $deep = true
     * @return array
     * @throws \Exception
     */
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
                
                // if($value instanceof Collection) $value = $value->toArray();
                if (is_object($value) && $deepcast)
                    $value = $this->toArray($value, $deepcast);

                $array[$reflProperty->getName()] = $value;
            }
        }

        return $array;
    }

    public function clone(object $entity, bool $deepcopy = true) 
    {
        return $this->hydrate(get_class($entity), $entity, [], $deepcopy);
    }

    public function fromProxy(Proxy $proxy)
    {
        $entity = str_strip(get_class($proxy), "Proxies\\__CG__\\");
        $classMetadata   = $this->entityManager->getClassMetadata($entity);

        $this->entityManager->detach($proxy);
        return $this->entityManager->find($entity, $this->getPropertyValue($proxy, begin($classMetadata->identifier)));
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

    protected function hydrateProperties(object $entity, array $data): self
    {
        $reflEntity = new ReflectionObject($entity);

        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));
        $skipFields = $classMetadata->identifier;

        foreach ($reflEntity->getProperties() as $reflProperty) {

            $propertyName = $reflProperty->getName();
            if($this->classMetadataManipulator->hasAssociation($entity, $propertyName))
                continue;

            switch($reflProperty->getType()) {

                case "array":
                    $this->setPropertyValue($entity, $propertyName, []);
                break;
            }

            $dataKey = $this->hydrateBy === self::HYDRATE_BY_FIELD ? $propertyName : $classMetadata->getColumnName($propertyName);
            if (array_key_exists($dataKey, $data) && !in_array($propertyName, $skipFields, true))
                $this->setPropertyValue($entity, $propertyName, $data[$dataKey], $reflEntity);
        }

        return $this;
    }

    protected function hydrateAssociations(mixed $entity, array $data, bool $deepcopy = true): self
    {
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));

        foreach ($classMetadata->associationMappings as $association => $mapping) {

            $associationData = $this->getAssociatedId($association, $mapping, $data);
            if (!empty($associationData)) {

                if ($this->classMetadataManipulator->isToOneSide($entity, $association))
                    $this->hydrateToOneAssociation($entity, $association, $mapping, $associationData);

                if ($this->classMetadataManipulator->isToManySide($entity, $association)) {

                    if($classMetadata->getFieldName($association) === $association && $deepcopy)
                        $this->setPropertyValue($entity, $association, new ArrayCollection());

                    $this->hydrateToManyAssociation($entity, $association, $mapping, $associationData, $deepcopy);
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

    protected function hydrateToOneAssociation(mixed $entity, string $propertyName, array $mapping, $value): self
    {
        $reflEntity = new ReflectionObject($entity);

        $toOneAssociationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value);
        if ($toOneAssociationObject !== null)
            $this->setPropertyValue($entity, $propertyName, $toOneAssociationObject, $reflEntity);

        return $this;
    }

    protected function hydrateToManyAssociation(mixed $entity, string $propertyName, array $mapping, $value, bool $deepcopy = true): self
    {
        $reflEntity = new ReflectionObject($entity);
        $values = is_array($value) ? $value : [$value];

        if(!$deepcopy) $associationObjects = $value;
        else {

            $associationObjects = [];
            foreach ($values as $value) {

                if($value instanceof Collection) {

                    $entityValue = $this->getPropertyValue($entity, $propertyName)->toArray();
                    $associationObjects = new ArrayCollection($entityValue + $value->toArray());

                } else if (is_array($value)) {

                    $associationObjects[] = $this->hydrate($mapping['targetEntity'], $value);

                } elseif ($associationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value)) {
                
                    $associationObjects[] = $associationObject;
                }
            }
        }

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