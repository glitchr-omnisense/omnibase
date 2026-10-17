<?php

namespace Base\Database\Type;

/**
 * UtcDateTimeImmutableType's mutable twin (`utc_datetime`): a \DateTime
 * written in UTC and read back as UTC, whatever zone PHP is in.
 *
 * With base.database.use_custom (the default) omnibase's plain `datetime` is
 * UTC already; this type says so by name, and holds without that option. It
 * leaves the object it is given untouched (DateTimeTypeUTC moves the entity's
 * own \DateTime to UTC as it writes it).
 *
 * omnibridge/doctrine's UtcDateTimeType, under omnibase's name: written and
 * read as it writes and reads (in UTC; unreadable, the DBAL's own exception),
 * so a site mapped by omnibase and an application mapped by omnibridge/doctrine
 * read each other's rows.
 */
final class UtcDateTimeType extends \Omnibridge\Doctrine\Type\UtcDateTimeType
{
    public const NAME = 'utc_datetime';
}
