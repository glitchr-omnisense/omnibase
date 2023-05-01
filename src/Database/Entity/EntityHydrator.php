<?php

namespace Base\Database\Entity;

use Base\Database\Entity\AggregateHydrator\PopulableInterface;
use Base\Database\Entity\AggregateHydrator\SerializableInterface;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\TranslationInterface;
use Base\Database\Type\SetType;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\DBAL\Types\ArrayType;

use Base\Service\Localizer;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionObject;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class EntityHydrator implements EntityHydratorInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    /**
     * @var array
     */
    protected array $reflProperties = [];

    public const HYDRATE_BY_FIELD = 1; // The keys in the data array are entity field names
    public const HYDRATE_BY_COLUMN = 2; // The keys in the data array are database column names

    /**
     * If true, then associations are filled only with reference proxies. This is faster than querying them from
     * database, but if the associated entity does not really exist, it will cause:
     * * The insert/update to fail, if there is a foreign key defined in database
     * * The record ind database also pointing to a non-existing record
     *
     * @var bool
     */
    protected bool $hydrateAssociationReferences = true;

    /*
     * Make sure the property has been hydrated (to avoid double hydratation between properties and associations)
     */
    protected array $hydratationMapping = [];

    /**
     * Aggregate methods: by default, it is "object properties" by "deep copy" method without fallback, but initializing properties
     */
    public const DEFAULT_AGGREGATE = 0b00000000000;
    public const CLASS_METHODS = 0b00000000001;
    public const OBJECT_PROPERTIES = 0b00000000010;
    public const PREVENT_ASSOCIATIONS = 0b00000000100;
    public const ARRAY_OBJECT = 0b00000001000;
    public const DEEPCOPY = 0b00000010000;
    public const CONSTRUCT = 0b00000100000;
    public const INITIALIZE = 0b00001000000;
    public const IGNORE_NULLS = 0b00010000000;
    public const AUTOTYPE = 0b00100000000;
    public const OBJECT_INHERITED = 0b01000000000;
    public const FETCH_ASSOCIATIONS = 0b10000000000;

    /**
     * @var PropertyAccessorInterface
     */
    protected PropertyAccessorInterface $propertyAccessor;

    public function __construct(EntityManagerInterface $entityManager, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->entityManager = $entityManager;

        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->enableMagicCall()->getPropertyAccessor();
    }

    public function hydrate(mixed $entity, null|array|object $data = [], array $fieldExceptions = [], int $aggregateModel = self::DEFAULT_AGGREGATE, ...$constructArguments): mixed
    {
        $reflClass = new ReflectionClass($entity);
        if (is_string($entity) && class_exists($entity)) {
            if (is_abstract($entity)) {
                throw new InvalidArgumentException("Cannot instantiate abstract class \"$entity\"");
            }

            if ($aggregateModel & self::CONSTRUCT) {
                $entity = new $entity(...$constructArguments);
            } else {
                $entity = $reflClass->newInstanceWithoutConstructor();
            }

            if ($aggregateModel & self::INITIALIZE) {
                foreach ($reflClass->getProperties() as $reflProperty) {
                    initialize_property($entity, $reflProperty->getName());
                }
            }
        }

        if (!is_object($entity)) {
            throw new Exception('Entity passed to ' . __CLASS__ . '::' . __FUNCTION__ . '() must be a class name or entity object');
        }

        $this->setDefaults($entity, $aggregateModel);

        $entityName = get_class($entity);
        if (is_object($data) && !$data instanceof Collection && !is_instanceof($entityName, get_class($data)) && ($aggregateModel & self::OBJECT_INHERITED)) {
            throw new Exception("\"" . get_class($data) . "\" data passed to " . __CLASS__ . "::" . __FUNCTION__ . "() must inherit from \"" . get_class($entity) . "\"");
        }

        $data = $this->dehydrate($data, $fieldExceptions);
        if ($data === null) {
            return null;
        }

        if (!$this->hydrateByArrayObject($entity, $data, $aggregateModel)) {
            $this->hydratationMapping = [];
            $this->hydrateProperties($entity, $data, $aggregateModel);
            $this->hydrateAssociations($entity, $data, $aggregateModel);
        }

        $this->bindAliases($entity);

        return $entity;
    }

    public function dehydrate(mixed $entity, array $fieldExceptions = [], int $aggregateModel = self::CLASS_METHODS | self::OBJECT_PROPERTIES): ?array
    {
        if ($entity === null) {
            return null;
        }

        $data = $entity ?? [];
        $data = $data instanceof Collection ? $data->toArray() : $data;
        if (is_object($data)) {
            $data = array_transforms(
                function ($k, $e) use ($aggregateModel, $data): ?array {

                    $varName = explode("\x00", $k);
                    $varName = last($varName);
                    if ($aggregateModel & self::CLASS_METHODS && $this->propertyAccessor->isReadable($data, $varName)) {
                        return [$varName, $this->propertyAccessor->getValue($data, $varName)];
                    }

                    if ($aggregateModel & self::OBJECT_PROPERTIES) {
                        return [$varName, $e instanceof ArrayCollection && $e->isEmpty() ? null : $e];
                    }

                    return null;
                },
                (array)$data
            );
        }

        return array_key_removes($data, ...$fieldExceptions);
    }

    public function fromProxy(Proxy $proxy)
    {
        $entity = str_strip(get_class($proxy), "Proxies\\__CG__\\");
        $classMetadata = $this->entityManager->getClassMetadata($entity);

        $this->entityManager->detach($proxy);
        return $this->entityManager->find($entity, $this->getPropertyValue($proxy, begin($classMetadata->identifier)));
    }

    public function toArray(object|array $objectOrArray, bool $deepcast = false): array
    {
        $array = [];

        if ($objectOrArray instanceof Collection) {
            $objectOrArray = $objectOrArray->toArray();
        }
        if (is_array($objectOrArray)) {
            $array = $objectOrArray;
            if ($deepcast) {
                foreach ($array as $propertyName => $value) {
                    $array[$propertyName] = is_object($value) ? $this->toArray($value, $deepcast) : $value;
                }
            }
        } elseif (is_object($objectOrArray)) {
            $reflectionClass = new ReflectionClass(get_class($objectOrArray));
            foreach ($reflectionClass->getProperties() as $reflProperty) {
                $reflProperty->setAccessible(true);

                $value = $reflProperty->getValue($objectOrArray);

                if (is_object($value) && $deepcast) {
                    $value = $this->toArray($value, $deepcast);
                }

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

        $reflData = new ReflectionObject($data);
        $metaData = $this->entityManager->getClassMetadata(get_class($data));

        foreach ($metaData->identifier as $id) {
            if (in_array($id, $metaEntity->identifier)) {
                $this->setPropertyValue($entity, $id, $this->getPropertyValue($data, $id, $reflData), $reflEntity);
            }
        }

        return $entity;
    }

    protected function hydrateByArrayObject(object $entity, array $data, int $aggregateModel): bool
    {
        // Hydrate by ArrayObject
        if ($aggregateModel & self::ARRAY_OBJECT) {
            if (class_implements_interface(PopulableInterface::class, $entity)) {
                $entity->populate($data);
                return true;
            } elseif (class_implements_interface(SerializableInterface::class, $entity)) {
                $entity->exchangeArray($data);
                return true;
            }
        }

        return false;
    }

    protected function bindAliases(object $entity): self
    {
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));
        foreach ($this->classMetadataManipulator->getFieldNames($classMetadata) as $alias => $column) {
            if ($alias == $column) {
                continue;
            }
            if (snake2camel($alias) == snake2camel($column)) {
                continue;
            }
            if (camel2snake($alias) == camel2snake($column)) {
                continue;
            }
            $fn = function () use ($alias, $column) {
                $aliasValue = $this->$alias;

                $columnValue = $this->$column;
                if ($aliasValue instanceof ArrayCollection && $columnValue instanceof ArrayCollection) {
                    $aliasValue = new ArrayCollection($columnValue->toArray() + $aliasValue->toArray());
                } elseif ($columnValue !== null) {
                    $aliasValue = $columnValue;
                }

                $this->$alias = &$this->$column; // Bind variable together..
                $this->$alias = $aliasValue;

                return $this;
            };

            $fnClosure = Closure::bind($fn, $entity, get_class($entity));
            $fnClosure();
        }

        return $this;
    }

    protected function setDefaults(object $entity, int $aggregateModel)
    {
        $reflEntity = new ReflectionObject($entity);
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));

        //
        // Set default values for fields
        foreach ($classMetadata->fieldMappings as $fieldName => $fieldMapping) {
            if ($this->getPropertyValue($entity, $fieldName) !== null) {
                continue;
            } // Only if null

            //
            // Default values for the specific array cases
            $doctrineType = $this->classMetadataManipulator->getDoctrineType($fieldMapping["type"]);
            if (is_instanceof($doctrineType, ArrayType::class) || is_instanceof($doctrineType, SetType::class)) {
                $this->setPropertyValue($entity, $fieldName, [], $reflEntity);
            }

            //
            // Advanced typing detection (autotype)
            $reflProperty = $reflEntity->hasProperty($fieldName) ? $reflEntity->getProperty($fieldName) : null;
            if ($aggregateModel & self::AUTOTYPE) {
                // Find field type
                $type = null;
                if ($this->classMetadataManipulator->hasField($entity, $fieldName)) {
                    $mapping = $this->classMetadataManipulator->getMapping($entity, $fieldName);
                    if ($mapping["nullable"]) {
                        continue;
                    } // Skip nullable elements

                    $type = $this->classMetadataManipulator->getTypeOfField($entity, $fieldName);
                } elseif ($reflProperty !== null) {
                    $type = $reflProperty->getType();
                }

                $value = match ($type) {
                    "array" => [],
                    "bool" => false,
                    "string" => "",
                    "number", "integer", "float" => 0,
                    default => null,
                };

                $this->setPropertyValue($entity, $fieldName, $value, $reflEntity);
            }
        }

        //
        // Set default values for associations
        foreach ($classMetadata->associationMappings as $fieldName => $associationMapping) {
            if ($this->getPropertyValue($entity, $fieldName) !== null) {
                continue;
            }

            if ($this->classMetadataManipulator->isToManySide($entity, $fieldName)) {
                $this->setPropertyValue($entity, $fieldName, new ArrayCollection(), $reflEntity);
            }
        }
    }

    protected function hydrateProperties(object $entity, array $data, int $aggregateModel): self
    {
        $reflEntity = new ReflectionObject($entity);
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));

        foreach ($data as $propertyName => $value) {
            if ($this->classMetadataManipulator->hasAssociation($entity, $propertyName)) {
                continue;
            }
            if ($value === null && $aggregateModel & self::IGNORE_NULLS) {
                continue;
            }

            //
            // Fetch associations
            $isId = str_ends_with($propertyName, "_id");
            $isUuid = str_ends_with($propertyName, "_uuid");
            $isSlug = str_ends_with($propertyName, "_slug");
            if ($aggregateModel & self::FETCH_ASSOCIATIONS && ($isId || $isUuid || $isSlug)) {
                continue;
            }

            //
            // Default behavior
            $aggregateFallback = !($aggregateModel & self::CLASS_METHODS);
            if ($aggregateModel & self::CLASS_METHODS && $this->propertyAccessor->isWritable($entity, $propertyName)) {
                try {
                    $this->propertyAccessor->setValue($entity, $propertyName, $value);
                } catch (AccessException $e) {
                }
                $this->markAsHydrated($entity, $propertyName);
            } elseif ($aggregateModel & self::OBJECT_PROPERTIES || $aggregateFallback) {
                $reflProperty = $reflEntity->hasProperty($propertyName) ? $reflEntity->getProperty($propertyName) : null;
                if ($reflProperty !== null) {
                    $propertyName = $reflProperty->getName();
                    if (!in_array($propertyName, $classMetadata->identifier, true)) {
                        $this->setPropertyValue($entity, $propertyName, $value, $reflEntity);
                        $this->markAsHydrated($entity, $propertyName);
                    }
                }
            }
        }

        return $this;
    }

    protected function resetHydratationMapping()
    {
        $this->hydratationMapping = [];
        return $this;
    }

    protected function isHydrated(mixed $entity, string $propertyName)
    {
        return in_array(spl_object_hash($entity) . "::" . $propertyName, $this->hydratationMapping);
    }

    protected function markAsHydrated(mixed $entity, string $propertyName)
    {
        $this->hydratationMapping[] = spl_object_hash($entity) . "::" . $propertyName;
        return $this;
    }

    protected function hydrateAssociations(mixed $entity, array $data, int $aggregateModel): self
    {
        if ($aggregateModel & self::PREVENT_ASSOCIATIONS) {
            return $this;
        }

        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));

        foreach ($data as $propertyName => $value) {
            //
            // Fetch associations
            $isId = str_ends_with($propertyName, "_id");
            $isUuid = str_ends_with($propertyName, "_uuid");
            $isSlug = str_ends_with($propertyName, "_slug");
            if ($aggregateModel & self::FETCH_ASSOCIATIONS && ($isId || $isUuid || $isSlug)) {
                $data = array_key_removes($data, $propertyName);
                $identifier = $this->classMetadataManipulator->resolveFieldPath($classMetadata->getName(), str_rstrip($propertyName, ["_id", "_uuid"]));
                $targetEntity = $this->classMetadataManipulator->fetchEntityName($classMetadata->getName(), $identifier);

                $repository = $this->classMetadataManipulator->getRepository($targetEntity);
                if ($repository != null && !array_key_exists($identifier, $data) && !array_key_exists($propertyName, $data)) {
                    if ($isId) {
                        $data[$identifier] = $repository->cacheOneById($value);
                    } elseif ($isUuid) {
                        $data[$identifier] = $repository->cacheOneByUuid($value);
                    } elseif ($isSlug) {
                        $data[$identifier] = $repository->cacheOneBySlug($value);
                    }
                }
            }
        }

        foreach ($data as $propertyName => $value) {
            if (!$this->classMetadataManipulator->hasAssociation($entity, $propertyName)) {
                continue;
            }

            $mapping = $classMetadata->associationMappings[$this->classMetadataManipulator->getFieldName($entity, $propertyName)];
            if ($this->classMetadataManipulator->isToOneSide($entity, $propertyName)) {
                $this->hydrateAssociationToOne($entity, $propertyName, $mapping, $value, $aggregateModel);
            }

            if ($this->classMetadataManipulator->isToManySide($entity, $propertyName)) {
                $this->hydrateAssociationToMany($entity, $propertyName, $mapping, $value, $aggregateModel);
            }
        }

        return $this;
    }

    protected function hydrateAssociationToOne(mixed $entity, string $propertyName, array $mapping, mixed $value, int $aggregateModel): self
    {
        if ($this->isHydrated($entity, $propertyName)) {
            return $this;
        }

        if (is_array($value)) {
            $association = $this->hydrate($mapping['targetEntity'], $value, [], $aggregateModel);
        } elseif (!is_object($value)) {
            $association = $this->findAssociation($mapping['targetEntity'], $value);
        } else {
            $association = $value;
        }

        $aggregateFallback = !($aggregateModel & self::CLASS_METHODS);
        if ($aggregateModel & self::CLASS_METHODS && $this->propertyAccessor->isWritable($entity, $propertyName)) {
            $value = $value instanceof $mapping['targetEntity'] ? $value : $this->hydrate($mapping['targetEntity'], $value, [], $aggregateModel);
            $this->propertyAccessor->setValue($entity, $propertyName, $value);
            $this->markAsHydrated($entity, $propertyName);
        } elseif ($aggregateModel & self::OBJECT_PROPERTIES || $aggregateFallback) {
            if ($aggregateModel & self::DEEPCOPY) {
                $association = clone $association;
            }
            $this->setPropertyValue($entity, $propertyName, $association, new ReflectionObject($entity));
            $this->markAsHydrated($entity, $propertyName);
        }

        return $this;
    }

    protected function hydrateAssociationToMany(mixed $entity, string $propertyName, array $mapping, mixed $values, int $aggregateModel): self
    {
        if ($this->isHydrated($entity, $propertyName)) {
            return $this;
        }

        if ($values !== null && !is_object($values) && !is_array($values)) {
            throw new Exception("Failed to turn \"$values\" into an association in \"" . get_class($entity) . "\". Did you pass an object?");
        }

        // Fetch or hydrate association
        $association = $values instanceof Collection ? $values : new ArrayCollection($values === null ? [] : (is_array($values) ? $values : [$values]));

        $array = $association->toArray();
        $association->clear();

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $entityValue = $this->getPropertyValue($entity, $propertyName);
                $value = $this->hydrate($entityValue->get($key) ?? $mapping['targetEntity'], $value, [], $aggregateModel);
            } elseif ($targetEntity = $this->findAssociation($mapping['targetEntity'], $value)) {
                $value = $targetEntity;
            }

            // Special case: the setter makes loosing the custom keyname (Perhaps one might implement an extends..)
            if (class_implements_interface($value, TranslationInterface::class)) {
                $key = Localizer::normalizeLocale($key);
                $value->setLocale($key);
            }

            $association->set($key, $value);
        }

        // $association = $associationNormalized;

        // Fix identification in owning side definition
        $isOwningSide = $mapping["isOwningSide"];
        if (!$isOwningSide) {
            $mappedBy = $mapping["mappedBy"];

            if ($this->classMetadataManipulator->isManyToSide($entity, $propertyName)) {
                $association = $association->toArray();
                foreach ($association as $entry) {
                    $collection = $this->propertyAccessor->getValue($entry, $mappedBy);
                    if ($collection instanceof Collection) {
                        $collection = $collection->toArray();
                    }

                    $collection[] = $entity;
                    $this->propertyAccessor->setValue($entry, $mappedBy, array_unique_object($collection));
                }
            } else {
                foreach ($association as $entry) {
                    if (is_string($entry)) {
                        continue;
                    }
                    $this->propertyAccessor->setValue($entry, $mappedBy, $entity);
                }
            }
        }

        // Commit association
        $aggregateFallback = !($aggregateModel & self::CLASS_METHODS);
        if ($aggregateModel & self::CLASS_METHODS && $this->propertyAccessor->isWritable($entity, $propertyName)) {
            $this->propertyAccessor->setValue($entity, $propertyName, $association);
            $this->markAsHydrated($entity, $propertyName);
        } elseif ($aggregateModel & self::OBJECT_PROPERTIES || $aggregateFallback) {
            if ($aggregateModel & self::DEEPCOPY) {
                $association = clone $association;
            }
            $this->setPropertyValue($entity, $propertyName, $association, new ReflectionObject($entity));
            $this->markAsHydrated($entity, $propertyName);
        }

        return $this;
    }

    protected function getProperty(mixed $entity, string $propertyName, ?ReflectionObject $reflEntity = null): mixed
    {
        return $this->getProperties($entity, $reflEntity)[$propertyName] ?? null;
    }

    protected function getProperties(mixed $entity, ?ReflectionObject $reflEntity = null): array
    {
        $reflEntity = $reflEntity === null ? new ReflectionObject($entity) : $reflEntity;
        if (array_key_exists($reflEntity->getName(), $this->reflProperties)) {
            return $this->reflProperties[$reflEntity->getName()];
        }

        $this->reflProperties[$reflEntity->getName()] = [];

        $classFamily = [];
        do {
            $classFamily[] = $reflEntity->getName();

            $this->reflProperties[$reflEntity->getName()] = [];
            foreach ($reflEntity->getProperties() as $reflProperty) {
                $reflProperty->setAccessible(true);
                $this->reflProperties[$reflEntity->getName()][$reflProperty->getName()] = $reflProperty;
            }

            foreach ($classFamily as $className) {
                $this->reflProperties[$className] = array_merge($this->reflProperties[$reflEntity->getName()], $this->reflProperties[$className]);
            }
        } while ($reflEntity = $reflEntity->getParentClass());

        return $this->reflProperties[get_class($entity)];
    }

    protected function getPropertyValues(mixed $entity, ?ReflectionObject $reflEntity = null): array
    {
        return array_map(fn($rp) => $rp->getValue($entity), $this->getProperties($entity, $reflEntity));
    }

    protected function getPropertyValue(mixed $entity, string $propertyName, ?ReflectionObject $reflEntity = null): mixed
    {
        return $this->getPropertyValues($entity, $reflEntity)[$propertyName] ?? null;
    }

    protected function setPropertyValue(mixed $entity, string $propertyName, $value, ?ReflectionObject $reflEntity = null): mixed
    {
        $reflEntity = $reflEntity === null ? new ReflectionObject($entity) : $reflEntity;

        $reflProperty = $this->getProperty($entity, $propertyName, $reflEntity);

        $reflProperty->setAccessible(true);
        $reflProperty->setValue($entity, $value);

        return $this;
    }

    protected function findAssociation($entityName, $identifier): mixed
    {
        if (is_object($identifier)) {
            return $identifier;
        }
        if (!$identifier) {
            return null;
        }

        if ($this->hydrateAssociationReferences) {
            return $this->entityManager->getReference($entityName, $identifier);
        }

        return $this->entityManager->find($entityName, $identifier);
    }


    public function getEntityFromData($className, $data): ?object
    {
        if ($data === null) {
            return null;
        }

        $fieldNames = $this->classMetadataManipulator->getFieldNames($className);
        $fields = array_intersect_key($data, array_flip($fieldNames));
        $associations = array_diff_key($data, array_flip($fieldNames));

        return $this->hydrate($className, array_merge($fields, $associations));
    }


    protected static $entitySerializer = null;

    public function getOriginalEntity($eventOrEntity)
    {
        if (!self::$entitySerializer) {
            self::$entitySerializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        }

        $data = $this->getOriginalEntityData($eventOrEntity);

        if (!$eventOrEntity instanceof LifecycleEventArgs) {
            $className = get_class($eventOrEntity);
        } else {
            $className = get_class($eventOrEntity->getObject());
        }

        return $this->hydrate($className, $data);
    }

    public function getOriginalEntityData($eventOrEntity)
    {
        $entity = $this->classMetadataManipulator->isEntity($eventOrEntity) ? $eventOrEntity : $eventOrEntity->getObject();

        $originalEntityData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($entity);
        if ($eventOrEntity instanceof PreUpdateEventArgs) {
            $event = $eventOrEntity;
            foreach ($event->getEntityChangeSet() as $field => $data) {
                $originalEntityData[$field] = $data[0];
            }
        }

        return $originalEntityData;
    }
}
