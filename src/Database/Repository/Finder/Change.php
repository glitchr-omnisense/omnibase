<?php

namespace Base\Database\Repository\Finder;

/**
 * A value updateBy() computes from the one the row holds, in the database (no row read, no race):
 *
 *     $topics->updateBy(['id' => $topic->getId()], ['views' => Change::add(1)]);
 *     $downloads->updateBy([...'path StartingWith $from/'...], ['path' => Change::replacePrefix($from, $to)]);
 */
final class Change
{
    public const ADD = 'add';
    public const REPLACE_PREFIX = 'replacePrefix';

    /** @param list<mixed> $arguments */
    private function __construct(public readonly string $operation, public readonly array $arguments)
    {
    }

    /** The number added to the field (a negative one takes it off). */
    public static function add(int|float $amount): self
    {
        return new self(self::ADD, [$amount]);
    }

    /** The field's leading $from replaced by $to (a path moved under another branch); the caller selects the rows starting with $from. */
    public static function replacePrefix(string $from, string $to): self
    {
        return new self(self::REPLACE_PREFIX, [$from, $to]);
    }
}
