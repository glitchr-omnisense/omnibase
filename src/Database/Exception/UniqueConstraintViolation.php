<?php

namespace Base\Database\Exception;

/**
 * A write refused by a unique constraint of the database, whatever the persistence: what a unit of work's flush()
 * throws in place of its driver's exception (kept as the previous one), for code that inserts and falls back on the
 * row that won the race.
 */
final class UniqueConstraintViolation extends \RuntimeException
{
    public static function from(\Throwable $previous): self
    {
        return new self($previous->getMessage(), 0, $previous);
    }
}
