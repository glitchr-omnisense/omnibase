<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Extension\ExtensionInlineInterface;
use Base\Database\Attribute\Backend\OrderColumnBackend;
use Base\Database\Mapping\EntityFields;
use Base\Database\Mapping\EntityShape;
use Base\Database\Type\Enumerations;
use Base\Database\Type\SetType;

use Base\Database\Entity\EntityValues;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OrderBy;
use Omnibridge\Persistence\Lifecycle;
use Exception;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OrderColumn extends AbstractAttribute implements ExtensionInlineInterface
{
    // Its mapping on Doctrine (its order field) is omnibridge/doctrine's DoctrineMappingHooks::orderColumn() since the
    // plan "omnibase agnostic"'s step F3: it was loadClassMetadata(), a hook typed on Doctrine's ClassMetadata.

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

            // field/type checks, on the metadata Doctrine is building (the reader's manipulator reads the shapes,
            // plan "omnibase agnostic", step E3): a set's column, or a to-many association.
            $field = EntityFields::aliases($object->getName())[$targetValue] ?? $targetValue;
            $type = $object->hasField($field) ? $object->getTypeOfField($field) : null;

            $isSet    = is_subclass_of((string) Enumerations::classOf($type), SetType::class);
            $isToMany = $object->hasAssociation($field) && $object->isCollectionValuedAssociation($field);

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

    /**
     * The ordered property's kind, on the entity's shape (plan "omnibase agnostic", step F2c): a to-many
     * association, or a set enumeration's column (the set itself, which gives its ordering keys); null
     * for anything else.
     *
     * @return array{0: bool, 1: ?SetType}|null [to many, the set]
     */
    protected function orderedKind(EntityShape $shape, ?string $property): ?array
    {
        if (null === $property) {
            return null;
        }

        $enumeration = Enumerations::classOf($shape->fieldType($property));
        $set = null !== $enumeration && is_subclass_of($enumeration, SetType::class) ? new $enumeration() : null;
        $isToMany = $shape->isToMany($property);

        return $set || $isToMany ? [$isToMany, $set] : null;
    }

    /**
     * The stored order written from the property's current order (the ids of a to-many association,
     * the keys of a set), or null when the property is not ordered or not initialised.
     */
    protected function positions(Lifecycle\EntityEvent $event, ?string $property): ?array
    {
        $entity = $event->entity;
        $shape = $this->shape($event, $entity);
        $kind = $this->orderedKind($shape, $property);
        if (!$kind) {
            return null;
        }
        [$isToMany, $set] = $kind;

        $property = EntityValues::of($event->unitOfWork)->fieldName($entity, $property) ?? $property;
        $reflProp = self::reflect($shape->className(), $property);
        if (!$reflProp->isInitialized($entity)) {
            return null; // skip if the property is not initialized
        }

        // An element persisted in the same flush has no id yet: it cannot be
        // positioned by id, and a null in the list broke every later read
        // (array_flip in OrderedArrayCollection::reorder). Left out, it
        // simply comes last - where it was added anyway.
        if ($isToMany) {
            return array_values(array_filter($reflProp->getValue($entity)->map(fn($o) => $o->getId())->toArray(), fn($id) => $id !== null));
        }

        return array_keys($set->getOrderingKeys($reflProp->getValue($entity)));
    }

    /** The property where it is declared, a parent's private one included (as Doctrine's metadata reflects it). */
    private static function reflect(string $class, string $property): ReflectionProperty
    {
        for ($declarer = $class; false !== $declarer; $declarer = get_parent_class($declarer)) {
            $reflClass = new \ReflectionClass($declarer);
            if ($reflClass->hasProperty($property)) {
                return $reflClass->getProperty($property);
            }
        }

        return new ReflectionProperty($class, $property); // throws: no such property
    }

    /** The neutral hook (plan "omnibase agnostic", step F2c): the stored order written before the insertion. */
    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
        $orderBy = $this->positions($event, $property);
        if (null === $orderBy) {
            return;
        }

        $orderBy = $this->type == "DESC" ? array_reverse($orderBy) : $orderBy;
        object_hydrate($event->entity, [$this->orderBy => json_encode($orderBy)]);
    }

    /** The neutral hook (plan "omnibase agnostic", step F2c): the stored order written before the update. */
    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
        $orderBy = $this->positions($event, $property);
        if (null === $orderBy) {
            return;
        }

        if(\is_identity($orderBy)) $orderBy = []; // avoid serializing large arrays of integers

        $orderBy = $this->type == "DESC" ? array_reverse($orderBy) : $orderBy;
        object_hydrate($event->entity, [$this->orderBy => json_encode($orderBy)]);

        $event->recompute();
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2c): the property sorted by its stored order - a
     * lazy association by the persistence when it can (OrderColumnBackend), the rest at once.
     */
    public function onPostLoad(Lifecycle\PostLoad $event, ?string $property = null): void
    {
        $entity = $event->entity;
        $shape = $this->shape($event, $entity);
        if (!$this->orderedKind($shape, $property)) {
            return;
        }

        $property = EntityValues::of($event->unitOfWork)->fieldName($entity, $property) ?? $property;
        try {
            $entityValue = self::reflect($shape->className(), $property)->getValue($entity) ?? [];
        }
        catch (\Throwable $e) { return; }

        $reflProp = self::reflect($shape->className(), $this->orderBy);

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
        if (!$this->backend($event, OrderColumnBackend::class)->orderLazily($entity, $entityValue, $orderBy)) {

            $entityValue = array_values(usort_key($entityValue, $orderBy));
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $propertyAccessor->setValue($entity, $property, $entityValue);
            object_hydrate($entity, [$this->orderBy => json_encode([])]);
        }
    }
}
