<?php

namespace Base\Database\Backend;

/**
 * An entity's row locked for writing while some work runs, in a transaction
 * (plan "omnibase agnostic", step F2d): the work sees no concurrent write of
 * that row - a double click on "like" makes one Like, not two. Doctrine's is a
 * pessimistic write lock (SELECT ... FOR UPDATE); registered per unit of work
 * in Base\Database\Attribute\Backend\Backends.
 */
interface RowLocks
{
    /**
     * Begins a transaction, locks the entity's row, runs the work and commits;
     * rolls back and throws again when the work throws. The work flushes what it
     * writes itself.
     *
     * @template R
     *
     * @param callable(): R $work
     *
     * @return R
     */
    public function locked(object $entity, callable $work): mixed;
}
