<?php

namespace Base\Database\Type;

use Base\Contract\Runtime;

/**
 * Moments in UTC, for the code around a `utc_datetime_immutable` column: the
 * present, and any date as the same moment in UTC - what a query parameter
 * compared with such a column must be when it is bound without its type.
 *
 *     ->setParameter('since', Utc::from($since))                                  // or, typed:
 *     ->setParameter('since', $since, UtcDateTimeImmutableType::NAME)
 */
final class Utc
{
    /** The zone moments are kept in: UTC, the one every persistence's date types write with. */
    public static function zone(): \DateTimeZone
    {
        return self::$zone ??= new \DateTimeZone('UTC');
    }

    private static ?\DateTimeZone $zone = null;

    /** The present on the application's clock (Runtime::clock(): the host's, a test's frozen one), in UTC. */
    public static function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface(Runtime::clock()->now())->setTimezone(self::zone());
    }

    /** Any date, as the same moment in UTC. */
    public static function from(?\DateTimeInterface $moment): ?\DateTimeImmutable
    {
        return $moment ? \DateTimeImmutable::createFromInterface($moment)->setTimezone(self::zone()) : null;
    }
}
