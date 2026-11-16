<?php

namespace Base\Database\Repository\Finder;

/**
 * A computed value of findValuesBy(): an aggregate over the rows of a group (count, sum, avg, min, max,
 * countDistinct), or a date bucket the rows are grouped by (day, hour - in UTC, as every moment is stored).
 *
 *     $credits->findValuesBy(['user', 'balance' => Aggregate::sum('minutes')], having: [...]);
 *     $views->findValuesBy(['day' => Aggregate::day('date'), 'views' => Aggregate::count()], ['path' => $path]);
 *
 * The rows are grouped by the plain values listed beside an aggregate (SQL's rule); a bucket is such a value.
 */
final class Aggregate
{
    public const COUNT = 'count';
    public const SUM = 'sum';
    public const AVG = 'avg';
    public const MIN = 'min';
    public const MAX = 'max';
    public const COUNT_DISTINCT = 'countDistinct';
    public const DAY = 'day';
    public const HOUR = 'hour';

    private function __construct(public readonly string $function, public readonly ?string $field)
    {
    }

    /** The rows of the group (null), or those whose field (a path) is not null. */
    public static function count(?string $field = null): self
    {
        return new self(self::COUNT, $field);
    }

    public static function sum(string $field): self
    {
        return new self(self::SUM, $field);
    }

    public static function avg(string $field): self
    {
        return new self(self::AVG, $field);
    }

    public static function min(string $field): self
    {
        return new self(self::MIN, $field);
    }

    public static function max(string $field): self
    {
        return new self(self::MAX, $field);
    }

    /** The distinct values of the field among the group's rows. */
    public static function countDistinct(string $field): self
    {
        return new self(self::COUNT_DISTINCT, $field);
    }

    /** The UTC day of a moment ("2026-11-15"): a value the rows are grouped by. */
    public static function day(string $field): self
    {
        return new self(self::DAY, $field);
    }

    /** The UTC hour of a moment ("2026-11-15 10:00:00"): a value the rows are grouped by. */
    public static function hour(string $field): self
    {
        return new self(self::HOUR, $field);
    }

    /** Whether it aggregates rows (else it is a bucket, a value of each row). */
    public function aggregates(): bool
    {
        return !\in_array($this->function, [self::DAY, self::HOUR], true);
    }
}
