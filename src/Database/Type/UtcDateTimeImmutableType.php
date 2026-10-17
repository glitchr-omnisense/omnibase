<?php

namespace Base\Database\Type;

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
 *
 * omnibridge/doctrine's UtcDateTimeImmutableType, under omnibase's name:
 * written and read as it writes and reads (in UTC; unreadable, the DBAL's
 * own exception).
 */
final class UtcDateTimeImmutableType extends \Omnibridge\Doctrine\Type\UtcDateTimeImmutableType
{
    public const NAME = 'utc_datetime_immutable';
}
