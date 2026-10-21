<?php

namespace Base\Database\Schema;

use Base\Database\Attribute\Alias;
use Base\Database\Attribute\OrderColumn;
use Base\Database\Type\SetType;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Draft;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\Declaration\ClassDeclaration;

/**
 * #[OrderColumn(orderBy: ...)]'s column, as the attribute gives it to
 * Doctrine: on a to-many relation or a set column, the field named by orderBy
 * is mapped if nothing maps it - a nullable json column, where the positions
 * are kept; a property that is an #[Alias] of a relation counts as that relation. The set types by name, to know a set column (omnibase's
 * enumerations: Base\Bridge\Doctrine\Types::enumerations()).
 */
final class OrderColumnRule implements Rule
{
    /** @param array<string, class-string> $types the column types by name, the sets among them */
    public function __construct(private readonly array $types = [])
    {
    }

    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        $aliases = Attributes::ofProperties($class->name, Alias::class);
        foreach (Attributes::ofProperties($class->name, OrderColumn::class) as $property => $attributes) {
            $orderBy = end($attributes)['orderBy'] ?? null;
            // A property that is an #[Alias] of a column (Gallery::$photos for Thread::$children) is read as that column.
            $target = isset($aliases[$property]) ? (string) (end($aliases[$property])['column'] ?? $property) : $property;
            $type = $class->fieldMappings[$target]['type'] ?? null;
            $isSet = null !== $type && is_subclass_of($this->types[$type] ?? '', SetType::class);
            $isToMany = in_array($class->associationMappings[$target]['type'] ?? null, ['one_to_many', 'many_to_many'], true);
            if (!$isSet && !$isToMany) {
                continue;
            }
            if (!$orderBy) {
                throw new \LogicException(sprintf("The '%s::orderBy' field is mandatory for association '%s'.", $class->name, $property));
            }
            if (!$class->hasField($orderBy)) {
                $class->mapField(['fieldName' => $orderBy, 'type' => 'json', 'nullable' => true]);
            }
        }
    }
}
