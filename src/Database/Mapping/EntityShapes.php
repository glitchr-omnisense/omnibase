<?php

namespace Base\Database\Mapping;

/**
 * The entities' shapes (plan "omnibase agnostic", step F2c): from a Description (DescriptionShapes), or
 * from the installed persistence (Base\Bridge\Doctrine\DoctrineShapes on Doctrine).
 */
interface EntityShapes
{
    /** Whether the class (or the entity's, a proxy seen through) is a mapped entity. */
    public function has(string|object $class): bool;

    /**
     * The shape of a mapped class: an entity, or a mapped superclass or an embeddable (which has() does
     * not count as entities).
     *
     * @throws \OutOfBoundsException for a class that is not mapped
     */
    public function shape(string|object $class): EntityShape;
}
