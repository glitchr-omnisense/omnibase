<?php

namespace Base\Database\Mapping;

use Omnibridge\Schema\Entity;
use Omnibridge\Schema\Relation;

/**
 * What the core's code reads of an entity's mapping, on no ORM (plan "omnibase agnostic", step F2c): its
 * class, root, identifier, fields and their types, associations and their kind, side and target, and
 * its discriminator - one Omnibridge\Schema\Entity of the application's Description.
 *
 * The behaviours and ClassMetadataManipulator ask it in place of Doctrine's ClassMetadata, through
 * EntityShapes: built from a Description (DescriptionShapes), or from what the installed persistence
 * resolved (Base\Bridge\Doctrine\DoctrineShapes, omnibridge/doctrine's MetadataDescription of each
 * class - the same description, SchemaDescriptionTest proves it).
 */
final class EntityShape
{
    public function __construct(public readonly Entity $entity)
    {
    }

    public function className(): string
    {
        return $this->entity->class;
    }

    /** The root of its inheritance, itself without one. */
    public function rootClass(): string
    {
        return $this->entity->root ?? $this->entity->class;
    }

    public function parentClass(): ?string
    {
        return $this->entity->parent;
    }

    public function table(): ?string
    {
        return $this->entity->table;
    }

    /** @return list<string> */
    public function identifier(): array
    {
        return array_values($this->entity->identifier);
    }

    /** The single identifier's property, null for a composite one or none. */
    public function primaryKey(): ?string
    {
        $identifier = array_values($this->entity->identifier);

        return 1 === \count($identifier) ? $identifier[0] : null;
    }

    /** @return list<string> */
    public function fieldNames(): array
    {
        return array_keys($this->entity->fields);
    }

    public function hasField(string $property): bool
    {
        return isset($this->entity->fields[$property]);
    }

    /** The field's column type by name ("string", "datetime", an enumeration's...), null for no field. */
    public function fieldType(string $property): ?string
    {
        return $this->entity->fields[$property]->type ?? null;
    }

    /** @return list<string> */
    public function associationNames(): array
    {
        return array_keys($this->entity->relations);
    }

    public function hasAssociation(string $property): bool
    {
        return isset($this->entity->relations[$property]);
    }

    /** Relation::ONE_TO_ONE, MANY_TO_ONE, ONE_TO_MANY or MANY_TO_MANY; null for no association. */
    public function associationKind(string $property): ?string
    {
        return $this->entity->relations[$property]->kind ?? null;
    }

    public function targetClass(string $property): ?string
    {
        return $this->entity->relations[$property]->target ?? null;
    }

    public function isToOne(string $property): bool
    {
        return \in_array($this->associationKind($property), [Relation::ONE_TO_ONE, Relation::MANY_TO_ONE], true);
    }

    public function isToMany(string $property): bool
    {
        return \in_array($this->associationKind($property), [Relation::ONE_TO_MANY, Relation::MANY_TO_MANY], true);
    }

    public function isOwningSide(string $property): bool
    {
        return $this->entity->relations[$property]->owning ?? false;
    }

    public function isInverseSide(string $property): bool
    {
        return $this->hasAssociation($property) && !$this->isOwningSide($property);
    }

    public function mappedBy(string $property): ?string
    {
        return $this->entity->relations[$property]->mappedBy ?? null;
    }

    public function inversedBy(string $property): ?string
    {
        return $this->entity->relations[$property]->inversedBy ?? null;
    }

    public function discriminatorColumn(): ?string
    {
        return $this->entity->inheritance?->column->name;
    }

    /** @return array<string, string> value => class */
    public function discriminatorMap(): array
    {
        return $this->entity->inheritance?->map ?? [];
    }

    public function discriminatorValue(): ?string
    {
        return $this->entity->inheritance?->value;
    }
}
