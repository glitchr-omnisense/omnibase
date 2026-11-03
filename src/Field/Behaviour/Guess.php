<?php

namespace Base\Field\Behaviour;

use Base\Database\Mapping\EntityShape;
use Base\Database\Type\Enumerations;
use Base\Database\Type\SetType;
use Base\Database\UnitOfWorks;
use Base\Field\FieldDescriptor;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Runtime;

/**
 * What a field's options leave out and the record's mapping says, as omnibase's Symfony types guess it
 * from Doctrine (FormGuessTrait, SlugType): read from the entity's shape on any host (the unit of work's
 * EntityShapes) - the class a select chooses among (an association's target, an enumeration column's
 * class) and whether it holds several (a to-many association, a set enumeration), and whether a slug
 * may be empty (its column nullable).
 */
final class Guess
{
    private function __construct()
    {
    }

    /** The field with what its record's mapping says set where its options say nothing; itself without a mapping. */
    public static function from(FieldDescriptor $field, ?string $dataClass): FieldDescriptor
    {
        $shape = self::shape($dataClass);
        $property = $field->getProperty();
        if (null === $shape || null === $property || $field->hasExplicitFormType()) {
            return $field;
        }
        $behaviour = Behaviours::of($field->getKind());
        $guessed = clone $field;
        if (\Base\Field\Kind::TRANSLATION === $field->getKind() && null === $field->getOption('translation_class') && null === $field->getOption('translatable_class')) {
            // TranslationType: the translations of the form's record (its data class).
            $guessed->setFormTypeOption('translatable_class', $dataClass);
        }
        // A property named by its alias (#[Alias]: an article's authors are its owners) is the aliased one's.
        $property = (new \Base\Database\Mapping\EntityFields(self::$shapes))->name($dataClass, $property) ?? $property;
        if ($behaviour instanceof Select && null === $field->getOption('class')) {
            if ($shape->hasAssociation($property) && null === $field->getOption('choices')) {
                $guessed->setFormTypeOption('class', $shape->targetClass($property));
                $guessed->setFormTypeOptionIfNotSet('multiple', $shape->isToMany($property));
            } elseif (null !== $enumeration = Enumerations::classOf($shape->fieldType($property))) {
                // An enumeration's column: its class, whose words and icons the entries show, the field's own choices kept.
                $guessed->setFormTypeOption('class', $enumeration);
                $guessed->setFormTypeOptionIfNotSet('multiple', is_a($enumeration, SetType::class, true));
            } elseif ($shape->hasField($property) && \in_array($shape->fieldType($property), ['array', 'json'], true)) {
                // FormGuessTrait::guessMultiple(): a list column (a translation's keywords, free tags) holds several.
                $guessed->setFormTypeOptionIfNotSet('multiple', true);
            }
        }
        if ($behaviour instanceof Select && null === $field->getOption('sortable') && !$field->getOption('multivalue')) {
            // FormGuessTrait::guessSortable(): a property kept in its order (#[OrderColumn], on itself or the column it aliases).
            $guessed->setFormTypeOption('sortable', self::ordered($dataClass, $field->getProperty()) || self::ordered($dataClass, $property));
        }
        if ($behaviour instanceof Slug && null === $field->isRequired() && $shape->hasField($property)) {
            // SlugType: a slug whose column may not be empty is required.
            $guessed->setRequired(!$shape->entity->field($property)->nullable);
        }

        return $guessed;
    }

    private static function ordered(string $class, string $property): bool
    {
        $attributes = \Base\Attributes\AttributeReader::getInstance()->getAttributes($class, [\Base\Database\Attribute\OrderColumn::class, \Base\Database\Attribute\Alias::class], [\Base\Attributes\AttributeReader::TARGET_PROPERTY]);
        $own = $attributes['property'][$class][$property] ?? [];
        if (!empty(array_filter_recursive($own))) {
            return true;
        }
        $alias = $own[\Base\Database\Attribute\Alias::class] ?? null;

        return \is_object($alias) && method_exists($alias, 'getAlias') && !empty(array_filter_recursive($attributes['property'][$class][$alias->getAlias()][\Base\Database\Attribute\OrderColumn::class] ?? []));
    }

    private static ?\Base\Database\Mapping\EntityShapes $shapes = null;

    private static function shape(?string $class): ?EntityShape
    {
        $unitOfWork = Runtime::find(UnitOfWork::class);
        if (null === $class || !$unitOfWork instanceof UnitOfWork) {
            return null;
        }
        try {
            $shapes = self::$shapes = UnitOfWorks::shapes($unitOfWork);

            return $shapes->has($class) ? $shapes->shape($class) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
