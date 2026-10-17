<?php

namespace Base\Database\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;

/**
 * UtcDateTimeImmutableType's mutable twin (`utc_datetime`): a \DateTime
 * written in UTC and read back as UTC, whatever zone PHP is in.
 *
 * With base.database.use_custom (the default) omnibase's plain `datetime` is
 * UTC already; this type says so by name, and holds without that option. It
 * leaves the object it is given untouched (DateTimeTypeUTC moves the entity's
 * own \DateTime to UTC as it writes it).
 */
final class UtcDateTimeType extends DateTimeType
{
    public const NAME = 'utc_datetime';

    /** DBAL 3 asks a type its name, and marks the column with it (DBAL 4 does neither). */
    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    /** Written as omnibridge/doctrine's UtcDateTimeType writes it (that class is final: delegated, not inherited). */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return self::bridge()->convertToDatabaseValue($value, $platform);
    }

    /** Read as omnibridge/doctrine's UtcDateTimeType reads it: in UTC; unreadable, the DBAL's own exception. */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTime
    {
        return self::bridge()->convertToPHPValue($value, $platform);
    }

    private static ?\Omnibridge\Doctrine\Type\UtcDateTimeType $bridge = null;

    private static function bridge(): \Omnibridge\Doctrine\Type\UtcDateTimeType
    {
        return self::$bridge ??= new \Omnibridge\Doctrine\Type\UtcDateTimeType();
    }
}
