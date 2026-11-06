<?php

namespace Base\Database\Entity;

use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\ColumnTypes;
use Base\Database\Entity\AggregateHydrator\PopulableInterface;
use Base\Database\Entity\AggregateHydrator\SerializableInterface;
use Base\Database\Entity\Extension\TranslationInterface;
use Base\Database\Mapping\EntityFields;
use Base\Database\Mapping\EntityShape;
use Base\Database\Mapping\EntityShapes;
use Base\Database\Repository\Repositories;
use Base\Database\Type\Enumerations;
use Base\Database\Type\SetType;
use Base\Database\UnitOfWorks;
use Base\Exception\UnsupportedBehaviourException;
use Base\Service\Localizer;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Omnibridge\Persistence\Lifecycle\EntityEvent;
use Omnibridge\Persistence\Lifecycle\PreUpdate;
use Omnibridge\Persistence\Snapshots;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Schema\Relation;
use ReflectionClass;
use ReflectionObject;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * An entity filled from data, or turned into data, and an entity rebuilt as it was read (plan "omnibase
 * agnostic", step F5c, item 3: on omnisense/omnibridge's UnitOfWork and the entities' shapes, no ORM's
 * metadata). What it reads of the mapping is the entity's EntityShape (fields, their types and
 * nullability, associations, their kind, side, target and mappedBy, the identifier) and its aliases
 * (EntityFields); what it asks of the persistence goes through the unit of work (references), its
 * repositories (UnitOfWorks::repositories(): an association by id, uuid or slug), its Snapshots (the
 * original values) and its ColumnTypes where it registers one (which types hold an array).
 *
 * Doctrine's entity manager and ClassMetadataManipulator are still accepted in place of the unit of work
 * and the shapes, deprecated until 6.0 (the entity manager made a unit of work by UnitOfWorks::of(), the
 * manipulator's shapes taken).
 */
class EntityHydrator implements EntityHydratorInterface
{
    protected UnitOfWork $unitOfWork;

    protected EntityShapes $shapes;

    protected EntityFields $fields;

    protected ?Repositories $repositories = null;

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
     * Make sure the property has been hydrated (to avoid double hydration between properties and associations)
     */
    protected array $hydrationMapping = [];

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
     * Made when first needed (CLASS_METHODS, an inverse side's owner): symfony/property-access's accessor loads
     * symfony/property-info, which a hydration through the properties alone does not need.
     *
     * @var PropertyAccessorInterface|null
     */
    protected ?PropertyAccessorInterface $propertyAccessor = null;

    protected function accessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor ??= PropertyAccess::createPropertyAccessorBuilder()->enableMagicCall()->getPropertyAccessor();
    }

    /**
     * @param UnitOfWork|EntityManagerInterface                 $unitOfWork the entity manager deprecated until 6.0
     * @param EntityShapes|ClassMetadataManipulator|null $shapes     the unit of work's persistence's by default (UnitOfWorks::shapes()); the manipulator deprecated until 6.0
     */
    public function __construct(UnitOfWork|EntityManagerInterface $unitOfWork, EntityShapes|ClassMetadataManipulator|null $shapes = null)
    {
        $this->unitOfWork = UnitOfWorks::of($unitOfWork);
        $this->shapes = $shapes instanceof EntityShapes ? $shapes : ($shapes?->getShapes() ?? UnitOfWorks::shapes($this->unitOfWork));
        $this->fields = new EntityFields($this->shapes);
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function getShapes(): EntityShapes
    {
        return $this->shapes;
    }

    protected function repositories(): Repositories
    {
        return $this->repositories ??= UnitOfWorks::repositories($this->unitOfWork);
    }

    protected function shape(object|string $entity): EntityShape
    {
        return $this->shapes->shape($entity);
    }

    /**
     * Whether a field of that type holds a PHP array (its default an empty one): the persistence's
     * ColumnTypes where it registers one, else `json` or a set enumeration.
     */
    protected function holdsArray(?string $type): bool
    {
        if (null === $type || '' === $type) {
            return false;
        }
        if (Backends::has($this->unitOfWork, ColumnTypes::class)) {
            return Backends::of($this->unitOfWork, ColumnTypes::class)->isArray($type);
        }

        $enumeration = Enumerations::classOf($type);

        return 'json' === $type || (null !== $enumeration && is_a($enumeration, SetType::class, true));
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
            $this->hydrationMapping = [];
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
                    $varName = array_end($varName);
                    if ($aggregateModel & self::CLASS_METHODS && $this->accessor()->isReadable($data, $varName)) {
                        return [$varName, $this->accessor()->getValue($data, $varName)];
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

    /**
     * The entity behind a proxy (or a lazy object), read from the database: refreshed through the unit of
     * work, which initialises it.
     */
    public function fromProxy(object $proxy): object
    {
        $this->unitOfWork->refresh($proxy);

        return $proxy;
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

    /**
     * @param bool $hydrateAssociationReferences
     * @return $this
     */
    public function setHydrateAssociationReferences(bool $hydrateAssociationReferences)
    {
        $this->hydrateAssociationReferences = $hydrateAssociationReferences;
        return $this;
    }

    protected function hydrateId(object $entity, object $data): object
    {
        $reflEntity = new ReflectionObject($entity);
        $identifier = $this->shape($entity)->identifier();

        $reflData = new ReflectionObject($data);
        foreach ($this->shape($data)->identifier() as $id) {
            if (in_array($id, $identifier)) {
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
        foreach ($this->fields->names($entity) as $alias => $column) {
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
        // The entity's shape (plan "omnibase agnostic", step F2c): its fields and associations as mapped.
        $shape = $this->shape($entity);

        //
        // Set default values for fields
        foreach ($shape->fieldNames() as $fieldName) {
            if ($this->getPropertyValue($entity, $fieldName) !== null) {
                continue;
            } // Only if null

            //
            // Default values for the specific array cases
            if ($this->holdsArray($shape->fieldType($fieldName))) {
                $this->setPropertyValue($entity, $fieldName, [], $reflEntity);
            }

            //
            // Advanced typing detection (autotype)
            $reflProperty = $reflEntity->hasProperty($fieldName) ? $reflEntity->getProperty($fieldName) : null;
            if ($aggregateModel & self::AUTOTYPE) {
                // Find field type
                $type = null;
                if ($shape->hasField($fieldName)) {
                    if ($shape->entity->fields[$fieldName]->nullable) {
                        continue;
                    } // Skip nullable elements

                    $type = $shape->fieldType($fieldName);
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
        foreach ($shape->associationNames() as $fieldName) {
            if ($this->getPropertyValue($entity, $fieldName) !== null) {
                continue;
            }

            if ($shape->isToMany($fieldName)) {
                $this->setPropertyValue($entity, $fieldName, new ArrayCollection(), $reflEntity);
            }
        }
    }

    protected function hydrateProperties(object $entity, array $data, int $aggregateModel): self
    {
        $reflEntity = new ReflectionObject($entity);

        foreach ($data as $propertyName => $value) {
            if ($this->fields->hasAssociation($entity, $propertyName)) {
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
            if ($aggregateModel & self::CLASS_METHODS && $this->accessor()->isWritable($entity, $propertyName)) {

                try { $this->accessor()->setValue($entity, $propertyName, $value); }
                catch (AccessException $e) {}
                $this->markAsHydrated($entity, $propertyName);

            } elseif ($aggregateModel & self::OBJECT_PROPERTIES || $aggregateFallback) {

                $reflProperty = $reflEntity->hasProperty($propertyName) ? $reflEntity->getProperty($propertyName) : null;
                if ($reflProperty !== null) {

                    $propertyName = $reflProperty->getName();
                    $this->setPropertyValue($entity, $propertyName, $value, $reflEntity);
                    $this->markAsHydrated($entity, $propertyName);
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function resetHydrationMapping()
    {
        $this->hydrationMapping = [];
        return $this;
    }

    /**
     * @param mixed $entity
     * @param string $propertyName
     * @return bool
     */
    protected function isHydrated(mixed $entity, string $propertyName)
    {
        return in_array(spl_object_hash($entity) . "::" . $propertyName, $this->hydrationMapping);
    }

    /**
     * @param mixed $entity
     * @param string $propertyName
     * @return $this
     */
    protected function markAsHydrated(mixed $entity, string $propertyName)
    {
        $this->hydrationMapping[] = spl_object_hash($entity) . "::" . $propertyName;
        return $this;
    }

    protected function hydrateAssociations(mixed $entity, array $data, int $aggregateModel): self
    {
        if ($aggregateModel & self::PREVENT_ASSOCIATIONS) {
            return $this;
        }

        $className = $this->shape($entity)->className();

        foreach ($data as $propertyName => $value) {
            //
            // Fetch associations
            $isId = str_ends_with($propertyName, "_id");
            $isUuid = str_ends_with($propertyName, "_uuid");
            $isSlug = str_ends_with($propertyName, "_slug");
            if ($aggregateModel & self::FETCH_ASSOCIATIONS && ($isId || $isUuid || $isSlug)) {
                $data = array_key_removes($data, $propertyName);
                $identifier = $this->fields->resolve($className, str_rstrip($propertyName, ["_id", "_uuid"]));
                $targetEntity = $this->fields->target($className, $identifier);

                $repository = $targetEntity ? $this->repositories()->of($targetEntity) : null;
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

        $shape = $this->shape($entity);
        foreach ($data as $propertyName => $value) {
            if (!$this->fields->hasAssociation($entity, $propertyName)) {
                continue;
            }

            // What the association's mapping said: its target, its side, the inverse side's mappedBy.
            $association = $this->fields->name($entity, $propertyName) ?? $propertyName;
            $mapping = [
                'targetEntity' => $shape->targetClass($association),
                'isOwningSide' => $shape->isOwningSide($association),
                'mappedBy' => $shape->mappedBy($association),
            ];
            if ($shape->isToOne($association)) {
                $this->hydrateAssociationToOne($entity, $propertyName, $mapping, $value, $aggregateModel);
            }

            if ($shape->isToMany($association)) {
                $this->hydrateAssociationToMany($entity, $propertyName, $mapping, $value, $aggregateModel);
            }
        }

        return $this;
    }

    /** @param array{targetEntity: class-string, isOwningSide: bool, mappedBy: ?string} $mapping */
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
        if ($aggregateModel & self::CLASS_METHODS && $this->accessor()->isWritable($entity, $propertyName)) {
            $value = $value instanceof $mapping['targetEntity'] ? $value : $this->hydrate($mapping['targetEntity'], $value, [], $aggregateModel);
            $this->accessor()->setValue($entity, $propertyName, $value);
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

    /** @param array{targetEntity: class-string, isOwningSide: bool, mappedBy: ?string} $mapping */
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
        if($aggregateModel & self::FETCH_ASSOCIATIONS) {

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

            // Fix identification in owning side definition
            $isOwningSide = $mapping["isOwningSide"];
            if (!$isOwningSide) {

                $mappedBy = $mapping["mappedBy"];

                if (in_array($this->shape($entity)->associationKind($this->fields->name($entity, $propertyName) ?? $propertyName), [Relation::MANY_TO_ONE, Relation::MANY_TO_MANY], true)) {

                    $association = $association->toArray();
                    foreach ($association as $entry) {
                        $collection = $this->accessor()->getValue($entry, $mappedBy);
                        if ($collection instanceof Collection) {
                            $collection = $collection->toArray();
                        }

                        $collection[] = $entity;
                        $this->accessor()->setValue($entry, $mappedBy, array_unique_object($collection));
                    }
                } else {
                    foreach ($association as $entry) {
                        if (is_string($entry)) {
                            continue;
                        }
                        $this->accessor()->setValue($entry, $mappedBy, $entity);
                    }
                }
            }
        }

        // Set association value
        $aggregateFallback = !($aggregateModel & self::CLASS_METHODS);
        if ($aggregateModel & self::CLASS_METHODS && $this->accessor()->isWritable($entity, $propertyName)) {
            $this->accessor()->setValue($entity, $propertyName, $association);
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
                if ($reflProperty->isPrivate()) {
                    continue;
                }
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

    /**
     * @param mixed $entity
     * @param string $propertyName
     * @param $value
     * @param ReflectionObject|null $reflEntity
     * @return mixed
     */
    protected function setPropertyValue(mixed $entity, string $propertyName, $value, ?ReflectionObject $reflEntity = null): mixed
    {
        $reflEntity = $reflEntity === null ? new ReflectionObject($entity) : $reflEntity;

        $reflProperty = $this->getProperty($entity, $propertyName, $reflEntity);

        $reflProperty->setAccessible(true);
        $reflProperty->setValue($entity, $value);

        return $this;
    }

    /**
     * @param $entityName
     * @param $identifier
     * @return mixed
     */
    protected function findAssociation($entityName, $identifier): mixed
    {
        if (is_object($identifier)) {
            return $identifier;
        }
        if (!$identifier) {
            return null;
        }

        if ($this->hydrateAssociationReferences) {
            return $this->unitOfWork->reference($entityName, $identifier);
        }

        return $this->repositories()->of($entityName)->find($identifier);
    }


    /**
     * @param $className
     * @param $data
     * @return object|null
     * @throws Exception
     */
    public function getEntityFromData($className, $data): ?object
    {
        if ($data === null) {
            return null;
        }

        $fieldNames = $this->fields->names($className);
        $fields = array_intersect_key($data, array_flip($fieldNames));
        $associations = array_diff_key($data, array_flip($fieldNames));

        return $this->hydrate($className, array_merge($fields, $associations));
    }


    /**
     * The entity as it was read or last flushed (its original values hydrated into a new one): of an
     * entity, of a lifecycle event's (omnisense/omnibridge's, or Doctrine's - deprecated until 6.0), a
     * PreUpdate's changes read as the values before.
     *
     * @param $eventOrEntity
     * @return mixed
     * @throws Exception
     */
    public function getOriginalEntity($eventOrEntity)
    {
        $data = $this->getOriginalEntityData($eventOrEntity);

        return $this->hydrate(get_class($this->entityOf($eventOrEntity)), $data);
    }

    /**
     * The original values of an entity, or of a lifecycle event's: its identifier, what the unit of
     * work's Snapshots remember, and before a PreUpdate the values its changes replace.
     *
     * @param $eventOrEntity
     * @return mixed[]
     * @throws UnsupportedBehaviourException when the persistence keeps no Snapshots
     */
    public function getOriginalEntityData($eventOrEntity)
    {
        $entity = $this->entityOf($eventOrEntity);
        $entityIdentifiers = [];

        foreach ($this->shape($entity)->identifier() as $id) {
            $entityIdentifiers[$id] = self::reflectProperty($entity, $id)->getValue($entity);
        }

        if (!$this->unitOfWork instanceof Snapshots) {
            throw UnsupportedBehaviourException::for(Snapshots::class, $this->unitOfWork);
        }
        $originalEntityData = array_merge($entityIdentifiers, $this->unitOfWork->original($entity));

        $changes = match (true) {
            $eventOrEntity instanceof PreUpdate => $eventOrEntity->changes(),
            // Doctrine's PreUpdateEventArgs, by name (deprecated until 6.0), and any lifecycle arguments that carry a
            // PreUpdate's changes the same way (omnibridge/eloquent's, for the entity dispatchers typed on
            // doctrine/persistence's LifecycleEventArgs; plan "omnibase agnostic", step F7c).
            is_object($eventOrEntity) && (is_a($eventOrEntity, 'Doctrine\ORM\Event\PreUpdateEventArgs') || (method_exists($eventOrEntity, 'getObject') && method_exists($eventOrEntity, 'getEntityChangeSet'))) => $eventOrEntity->getEntityChangeSet(),
            default => [],
        };
        foreach ($changes as $field => $data) {
            $originalEntityData[$field] = $data[0];
        }

        return $originalEntityData;
    }

    /** The entity itself, or a lifecycle event's: omnisense/omnibridge's (entity), Doctrine's (getObject()). */
    protected function entityOf(object $eventOrEntity): object
    {
        if ($eventOrEntity instanceof EntityEvent) {
            return $eventOrEntity->entity;
        }
        if ($this->fields->mapped($eventOrEntity)) {
            return $eventOrEntity;
        }

        return method_exists($eventOrEntity, 'getObject') ? $eventOrEntity->getObject() : $eventOrEntity;
    }

    /** The property where it is declared (a parent's private one included), as the mapping reflects it. */
    private static function reflectProperty(object $entity, string $property): \ReflectionProperty
    {
        for ($declarer = get_class($entity); false !== $declarer; $declarer = get_parent_class($declarer)) {
            $reflClass = new \ReflectionClass($declarer);
            if ($reflClass->hasProperty($property) && $reflClass->getProperty($property)->getDeclaringClass()->getName() === $declarer) {
                return $reflClass->getProperty($property);
            }
        }

        return new \ReflectionProperty($entity, $property);
    }
}
