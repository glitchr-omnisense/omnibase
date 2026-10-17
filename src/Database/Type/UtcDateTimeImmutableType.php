<?php

namespace Base\Database\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;

/**
 * A moment, written in UTC and read back as UTC (`utc_datetime_immutable`).
 *
 * omnibase sets PHP's time zone per visitor (BaseBundle::boot(), from the
 * `timezone` cookie). Doctrine's own `datetime_immutable` writes the wall
 * clock of whatever zone the object carries and reads it back in PHP's
 * default one: the same instant is stored at different hours by two
 * requests, and comes back as another moment to a visitor elsewhere.
 * omnibase's `datetime` is UTC already (DateTimeTypeUTC); this is its
 * immutable twin, declared on a column that holds a moment:
 *
 *     #[ORM\Column(type: UtcDateTimeImmutableType::NAME)]
 *     private \DateTimeImmutable $sentAt;
 *
 * The column is the platform's DATETIME, as `datetime_immutable`'s: moving a
 * column from one type to the other changes no schema, only what its rows
 * mean (docs/20-architecture/time.md).
 *
 * What comes back is in UTC; show it with Twig's |date (PHP's zone: the
 * visitor's) or ->setTimezone(). A wall clock that is no moment (a
 * restaurant's reservation at 20:00, an opening hour) is not for this type.
 */
final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    public const NAME = 'utc_datetime_immutable';

    /** DBAL 3 asks a type its name, and marks the column with it (DBAL 4 does neither). */
    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    /** Written as omnibridge/doctrine's UtcDateTimeImmutableType writes it (that class is final: delegated, not inherited). */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return self::bridge()->convertToDatabaseValue($value, $platform);
    }

    /** Read as omnibridge/doctrine's UtcDateTimeImmutableType reads it: in UTC; unreadable, the DBAL's own exception. */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        return self::bridge()->convertToPHPValue($value, $platform);
    }

    private static ?\Omnibridge\Doctrine\Type\UtcDateTimeImmutableType $bridge = null;

    private static function bridge(): \Omnibridge\Doctrine\Type\UtcDateTimeImmutableType
    {
        return self::$bridge ??= new \Omnibridge\Doctrine\Type\UtcDateTimeImmutableType();
    }
}
