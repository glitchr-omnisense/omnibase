<?php

namespace Base\Enum;

use Base\Database\Types\EnumType;

class ThreadState extends EnumType
{
    const APPROVED  = "STATE_APPROVED";
    const PENDING   = "STATE_PENDING";
    const REJECTED  = "STATE_REJECTED";
    const DELETED   = "STATE_DELETED";

    const SECRET    = "STATE_SECRET";
    const DRAFT     = "STATE_DRAFT";
    const FUTURE    = "STATE_FUTURE";
    const PUBLISHED = "STATE_PUBLISHED";

    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        $arrays[] = [
            self::PUBLISHED => "fas fa-fw fa-eye",
            self::DRAFT     => "fas fa-fw fa-drafting-compass",
            self::FUTURE    => "fas fa-fw fa-spinner",
            self::SECRET    => "fas fa-fw fa-eye-slash",

            self::APPROVED  => "fas fa-fw fa-check-circle",
            self::PENDING   => "fas fa-fw fa-pause-circle",
            self::REJECTED  => "fas fa-fw fa-times-circle",
            self::DELETED   => "fas fa-fw fa-ban",
        ];

        return parent::getIcons($pos, ...$arrays);
    }
}