<?php

namespace Base\Database\Type;

/**
 * Doctrine's `datetime` (and `datetimetz`) as omnibase overrides them with
 * base.database.use_custom (BaseBundle::boot()): a \DateTime moved to UTC as
 * it is written, read back as UTC.
 *
 * The conversion is omnibridge/doctrine's DateTimeType, the one another
 * framework reading the same database registers (Omnibridge\Doctrine\Types):
 * one place says how a moment is stored. This name stays the type omnibase
 * registers, for the applications and bundles that name it.
 */
class DateTimeTypeUTC extends \Omnibridge\Doctrine\Type\DateTimeType
{
}
