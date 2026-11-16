<?php

namespace Base\Database\Exception;

/**
 * A query on a table the database does not have (a schema not created or not migrated yet), whatever the
 * persistence: what a finder throws in place of its driver's exception (kept as the previous one), for code that
 * must run before the schema exists (a warm-up, an installer).
 */
final class TableNotFound extends \RuntimeException
{
    public static function from(\Throwable $previous): self
    {
        return new self($previous->getMessage(), 0, $previous);
    }
}
