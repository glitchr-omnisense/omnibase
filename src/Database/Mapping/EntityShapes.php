<?php

namespace Base\Database\Mapping;

/**
 * The entities' shapes (plan "omnibase agnostic", step F2c): from a Description (DescriptionShapes), or
 * from the installed persistence (Base\Bridge\Doctrine\DoctrineShapes on Doctrine).
 *
 * Both list the mapped entities' classes as well, classes(): list<string> (step B1d). Not declared here yet: an
 * implementation written against this interface before would no longer load; it is at 6.0 (step S done).
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
