<?php

namespace Base\Database\Mapping;

use Base\Cache\Abstract\AbstractLocalCache;
use Base\Database\Entity\EntityValues;
use Base\Database\Type\EnumType;
use Base\Database\Type\Enumerations;
use Base\Database\Type\SetType;
use Base\Database\UnitOfWorks;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Schema\Relation;

/**
 * What omnibase's AttributeReader and the attributes ask of the entities' mapping, read from the entities' shapes
 * - the Description (Omnibridge\Schema) a persistence maps them with - and their completors, on no ORM (plan "omnibase
 * agnostic", step E3). omnibridge/doctrine's ClassMetadataManipulator answered them from Doctrine's ClassMetadataFactory,
 * so the reader, the behaviours on writes and the aliases (#[Alias]) needed doctrine/orm installed whatever the
 * persistence; it extends this class now and keeps Doctrine's own (the mappings, the entity manager, the forms').
 *
 * Built on a unit of work (the persistence's shapes, UnitOfWorks::shapes(), asked when first needed: the reader is made
 * before any metadata may be loaded) or on the shapes themselves. The answers are those ClassMetadataManipulator gives
 * on Doctrine's metadata, method by method (MetadataManipulatorTest compares them for every class of lesringostars and
 * of the core's harness):
 * - a class is an entity when it has a shape - an entity, a mapped superclass or an embeddable, as Doctrine's
 *   isTransient() said -, a proxy or a lazy object by its parent;
 * - a name is an alias (its completor's `aliasNames`), a column's name or an association's (getFieldNames());
 * - an association's kind is Doctrine's number (ONE_TO_ONE 1, MANY_TO_ONE 2, ONE_TO_MANY 4, MANY_TO_MANY 8);
 * - the values are read and written as Base\Database\Entity\EntityValues does.
 * getDoctrineType() answers an enumeration's type only (an instance of the enumeration, as Doctrine's EnumTypeAdapter
 * gave it back), null for any other: no DBAL type exists here.
 */
class MetadataManipulator extends AbstractLocalCache
{
    /** Doctrine's numbers of the association kinds (ClassMetadata::ONE_TO_ONE...), as getAssociationType() answers. */
    public const ASSOCIATION_KINDS = [
        Relation::ONE_TO_ONE => 1,
        Relation::MANY_TO_ONE => 2,
        Relation::ONE_TO_MANY => 4,
        Relation::MANY_TO_MANY => 8,
    ];

    protected ?EntityShapes $shapes = null;
    private ?UnitOfWork $unitOfWork = null;
    private ?EntityFields $fields = null;
    private ?EntityValues $values = null;

    public function __construct(UnitOfWork|EntityShapes $source)
    {
        if ($source instanceof EntityShapes) {
            $this->shapes = $source;
        } else {
            $this->unitOfWork = $source;
        }
    }

    /**
     * The local cache a subclass keeps (omnibridge/doctrine's ClassMetadataManipulator): AbstractLocalCache's constructor,
     * which this class's own replaces.
     */
    protected function useCache(string $cacheDir, ?string $buildDir = null): void
    {
        parent::__construct($cacheDir, $buildDir);
    }

    /** The entities' shapes this manipulator reads. */
    public function getShapes(): EntityShapes
    {
        return $this->shapes ??= UnitOfWorks::shapes($this->unitOfWork ?? throw new \LogicException('A MetadataManipulator needs a unit of work or the shapes.'));
    }

    /** The shape of an entity or an entity class, null for anything else. */
    public function getShape(null|string|object $entityOrClass): ?EntityShape
    {
        if (null === $entityOrClass || !$this->fields()->mapped($entityOrClass)) {
            return null;
        }

        return $this->getShapes()->shape($entityOrClass);
    }

    public function isEntity($entityOrClassOrMetadata): bool
    {
        if (!\is_string($entityOrClassOrMetadata) && !\is_object($entityOrClassOrMetadata)) {
            return false;
        }
        if (\is_string($entityOrClassOrMetadata) && !class_exists($entityOrClassOrMetadata)) {
            return false;
        }

        return $this->fields()->mapped($entityOrClassOrMetadata);
    }

    public function isEnumType($entityOrClassOrMetadata): bool
    {
        if (\is_object($entityOrClassOrMetadata)) {
            $entityOrClassOrMetadata = $entityOrClassOrMetadata::class;
        }

        return \is_string($entityOrClassOrMetadata) && is_subclass_of($entityOrClassOrMetadata, EnumType::class);
    }

    public function isSetType($entityOrClassOrMetadata): bool
    {
        if (\is_object($entityOrClassOrMetadata)) {
            $entityOrClassOrMetadata = $entityOrClassOrMetadata::class;
        }

        return \is_string($entityOrClassOrMetadata) && is_subclass_of($entityOrClassOrMetadata, SetType::class);
    }

    /** A PHP enum (backed or not), as opposed to omnibase's own EnumType / SetType column types. */
    public function isNativeEnum(mixed $class): bool
    {
        if ($class instanceof \UnitEnum) {
            return true;
        }

        return \is_string($class) && enum_exists($class);
    }

    /** The PHP enum a column holds, null for any other column. */
    public function getEnumClass(null|string|object $entityOrClassOrMetadata, string $fieldName): ?string
    {
        $shape = $this->getShape($entityOrClassOrMetadata);
        if (null === $shape) {
            return null;
        }
        $fieldName = $this->getFieldName($entityOrClassOrMetadata, $fieldName) ?? $fieldName;
        $enum = $shape->entity->fields[$fieldName]->enum ?? null;

        return \is_string($enum) && enum_exists($enum) ? $enum : null;
    }

    /**
     * An enumeration's column type: an instance of the enumeration (UserRole, a SetType...), as Doctrine's type
     * registry gave it back; null for any other type.
     *
     * @return EnumType|string|null
     */
    public function getDoctrineType(?string $type)
    {
        if (!$type) {
            return $type;
        }
        $enumeration = Enumerations::classOf($type);

        return null === $enumeration ? null : new $enumeration();
    }

    /**
     * The identifier's single field; refused for a composite identifier or none, as Doctrine's
     * getSingleIdentifierFieldName().
     *
     * @return string|null
     */
    public function getPrimaryKey($entity)
    {
        $shape = $this->getShape($entity);
        if (null !== $shape && 1 !== \count($shape->identifier())) {
            throw new \RuntimeException(sprintf('The class %s has no single identifier field (%s).', $shape->className(), implode(', ', $shape->identifier()) ?: 'none'));
        }

        return $shape?->primaryKey();
    }

    public function getDiscriminatorColumn($entity): ?string
    {
        return $this->getShape($entity)?->discriminatorColumn();
    }

    public function getDiscriminatorValue($entity): ?string
    {
        return $this->getShape($entity)?->discriminatorValue();
    }

    public function getDiscriminatorMap($entity): array
    {
        return $this->getShape($entity)?->discriminatorMap() ?? [];
    }

    public function getRootEntityName($entity): ?string
    {
        return $this->getShape($entity)?->rootClass();
    }

    public function getFieldValue($entity, string|array $fieldPath): mixed
    {
        return $this->values()->get($entity, $fieldPath);
    }

    /** @return $this|false */
    public function setFieldValue($entity, string|array $fieldPath, $value)
    {
        if (!\is_object($entity) || !$this->isEntity($entity)) {
            return false;
        }
        $fieldPath = \is_array($fieldPath) ? implode('.', $fieldPath) : $fieldPath;
        $this->values()->set($entity, $this->getFieldName($entity, $fieldPath) ?? $fieldPath, $value);

        return $this;
    }

    public function hasField(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return null !== $this->getFieldName($entityOrClassOrMetadata, $fieldName);
    }

    public function hasProperty(null|string|object $entityOrClassOrMetadata, string $fieldName): ?string
    {
        return property_exists($entityOrClassOrMetadata, $fieldName);
    }

    /** @return mixed */
    public function getPropertyValue($entity, string $property)
    {
        return $this->values()->property($entity, $property);
    }

    /** @return $this */
    public function setPropertyValue($entity, string $property, $value)
    {
        $this->values()->setProperty($entity, $property, $value);

        return $this;
    }

    /** @return string|null the entity class whose folder a field's files go to (Uploader's path) */
    public function getDeclaringEntity(null|string|object $entityOrClassOrMetadata, $fieldPath)
    {
        if (null === $entityOrClassOrMetadata) {
            return null;
        }

        // An alias is its property's (ClassMetadataManipulator read the property's mapping).
        $fieldPath = (string) $fieldPath;
        if (!str_contains($fieldPath, '.')) {
            $fieldPath = $this->getFieldName($entityOrClassOrMetadata, $fieldPath) ?? $fieldPath;
        }

        return $this->values()->declaringEntity($entityOrClassOrMetadata, $fieldPath);
    }

    /** @return string|null an association's target, a field's enumeration (a PHP enum, an EnumType or a SetType) */
    public function getTargetClass(null|string|object $entityOrClassOrMetadata, $fieldName)
    {
        $fieldName = $this->getFieldName($entityOrClassOrMetadata, $fieldName) ?? $fieldName;
        if ($this->hasAssociation($entityOrClassOrMetadata, $fieldName)) {
            return $this->getAssociationTargetClass($entityOrClassOrMetadata, $fieldName);
        } elseif ($this->hasField($entityOrClassOrMetadata, $fieldName)) {
            if (null !== $enumClass = $this->getEnumClass($entityOrClassOrMetadata, $fieldName)) {
                return $enumClass;
            }

            $type = $this->getDoctrineType($this->getTypeOfField($entityOrClassOrMetadata, $fieldName) ?: null);
            if ($this->isEnumType($type) || $this->isSetType($type)) {
                return $type::class;
            }
        }

        return null;
    }

    /**
     * A field's column type by its name; false where the class is no entity or the path goes through an association,
     * as ClassMetadataManipulator answered (a dotted path read the association as a field, then nothing).
     *
     * @return false|string|null
     */
    public function getTypeOfField(null|string|object $entityOrClassOrMetadata, string $property)
    {
        $shape = $this->getShape($entityOrClassOrMetadata);
        if (null === $shape) {
            return false;
        }

        if (($dot = strpos($property, '.')) > 0) {
            $field = trim(substr($property, 0, $dot));
            $field = $this->getFieldName($entityOrClassOrMetadata, $field) ?? $field;
            if (!$shape->hasAssociation($field)) {
                throw new \Exception("No association found for field \"$field\" in \"".(\is_object($entityOrClassOrMetadata) ? $entityOrClassOrMetadata::class : $entityOrClassOrMetadata).'"');
            }

            return false;
        }

        return $shape->hasField($property) ? $shape->fieldType($property) : null;
    }

    public function fetchEntityName(string $entityName, array|string $fieldPath): ?string
    {
        return $this->fields()->target($entityName, $fieldPath);
    }

    public function isAlias(null|object|string $entityOrClassOrMetadata, array|string $fieldPath): bool
    {
        return $this->getFieldName($entityOrClassOrMetadata, $fieldPath) != $fieldPath;
    }

    public function getFieldName(null|object|string $entityOrClassOrMetadata, array|string $fieldName): ?string
    {
        return $this->getFieldNames($entityOrClassOrMetadata)[$fieldName] ?? null;
    }

    /** @return array<string, string>|null every name of the class's properties, name => property */
    public function getFieldNames(null|object|string $entityOrClassOrMetadata): ?array
    {
        if (null === $entityOrClassOrMetadata || !$this->fields()->mapped($entityOrClassOrMetadata)) {
            return null;
        }

        return $this->fields()->names($entityOrClassOrMetadata);
    }

    public function resolveFieldPath(null|object|string $entityOrClassOrMetadata, array|string $fieldPath): ?string
    {
        if (null === $entityOrClassOrMetadata) {
            return null;
        }

        return $this->fields()->resolve($entityOrClassOrMetadata, $fieldPath);
    }

    public function getAssociationTargetClass(null|string|object $entityOrClassOrMetadata, string $fieldName): string
    {
        $shape = $this->getShape($entityOrClassOrMetadata) ?? throw new \InvalidArgumentException('Entity expected, "'.get_debug_type($entityOrClassOrMetadata).'" is not an entity.');

        return (string) $shape->targetClass($this->getFieldName($entityOrClassOrMetadata, $fieldName) ?? $fieldName);
    }

    /** @return bool */
    public function hasAssociation(null|string|object $entityOrClassOrMetadata, ?string $fieldName)
    {
        if (null === $entityOrClassOrMetadata) {
            return false;
        }

        return $this->fields()->hasAssociation($entityOrClassOrMetadata, $fieldName);
    }

    /** @return bool */
    public function hasRecursiveAssociation(null|string|object $entityOrClassOrMetadata, ?string $fieldName)
    {
        if (null === $fieldName || !$this->hasAssociation($entityOrClassOrMetadata, $fieldName)) {
            return false;
        }
        $shape = $this->getShape($entityOrClassOrMetadata);
        $fieldName = $this->getFieldName($entityOrClassOrMetadata, $fieldName) ?? $fieldName;
        $relation = $shape->entity->relations[$fieldName];

        return $relation->target === ($relation->inheritedFrom ?? $shape->className());
    }

    public function isOwningSide(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return (bool) $this->getShape($entityOrClassOrMetadata)?->isOwningSide($this->getFieldName($entityOrClassOrMetadata, $fieldName) ?? $fieldName);
    }

    public function isInverseSide(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return (bool) $this->getShape($entityOrClassOrMetadata)?->isInverseSide($this->getFieldName($entityOrClassOrMetadata, $fieldName) ?? $fieldName);
    }

    public function isToOneSide(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [1, 2], true);
    }

    public function isToManySide(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [4, 8], true);
    }

    public function isManyToSide(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [2, 8], true);
    }

    public function isOneToSide(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [4, 1], true);
    }

    public function isManyToMany(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return 8 === $this->getAssociationType($entityOrClassOrMetadata, $fieldName);
    }

    public function isOneToMany(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return 4 === $this->getAssociationType($entityOrClassOrMetadata, $fieldName);
    }

    public function isManyToOne(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return 2 === $this->getAssociationType($entityOrClassOrMetadata, $fieldName);
    }

    public function isOneToOne(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        return 1 === $this->getAssociationType($entityOrClassOrMetadata, $fieldName);
    }

    /** @return false|int Doctrine's number of the association's kind, false for no association */
    public function getAssociationType(null|string|object $entityOrClassOrMetadata, string $fieldName)
    {
        $shape = $this->getShape($entityOrClassOrMetadata);
        if (null === $shape) {
            return false;
        }
        $kind = $shape->associationKind($this->getFieldName($entityOrClassOrMetadata, $fieldName) ?? $fieldName);

        return null === $kind ? false : self::ASSOCIATION_KINDS[$kind];
    }

    /** @return list<string> the mapped entities' classes */
    public function getAllClassNames()
    {
        $shapes = $this->getShapes();

        return method_exists($shapes, 'classes') ? $shapes->classes() : [];
    }

    /** The completor of a mapped class (its attributes' additions: aliases, hierarchy), null for any other. */
    public function getClassMetadataCompletor(null|string|object $entityOrClassOrMetadata): ?Completor
    {
        $shape = $this->getShape($entityOrClassOrMetadata);

        return null === $shape ? null : Completors::of($shape->className());
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return [];
    }

    protected function fields(): EntityFields
    {
        $shapes = $this->getShapes();
        if (null === $this->fields || $this->fields->shapes() !== $shapes) {
            $this->fields = new EntityFields($shapes);
        }

        return $this->fields;
    }

    protected function values(): EntityValues
    {
        $shapes = $this->getShapes();
        if (null === $this->values || $this->values->shapes() !== $shapes) {
            $this->values = new EntityValues($shapes);
        }

        return $this->values;
    }
}
