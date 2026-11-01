<?php

namespace Base\Database\Backend;

/**
 * One field of an entity's row written only if it still holds a value, in a
 * single statement (plan "omnibase agnostic", step F2d): of two requests
 * claiming the same sign-in link at once, one wins. Doctrine's is a DQL UPDATE
 * ... WHERE id = :id AND field = :from; registered per unit of work in
 * Base\Database\Attribute\Backend\Backends.
 */
interface ConditionalUpdates
{
    /**
     * Sets the field of the entity's row to $to where it is $from; the entity
     * itself is not changed.
     *
     * @return bool whether the row was written (false: another wrote it first, or it is gone)
     */
    public function compareAndSet(object $entity, string $field, mixed $from, mixed $to): bool;
}
