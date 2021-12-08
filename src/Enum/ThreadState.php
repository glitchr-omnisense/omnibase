<?php

namespace Base\Enum;

use Base\Database\Types\EnumType;

class ThreadState extends EnumType
{
    const PUBLISHED = "STATE_PUBLISHED";
    const DRAFT     = "STATE_DRAFT";
    const SECRET    = "STATE_SECRET";
    const FUTURE    = "STATE_FUTURE";
    const ARCHIVED  = "STATE_ARCHIVED";
    
    const APPROVED   = "STATE_APPROVED";
    const PENDING    = "STATE_PENDING";
    const SUSPENDED  = "STATE_SUSPENDED";
    const REJECTED   = "STATE_REJECTED";
    const DELETED    = "STATE_DELETED";

    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        $arrays[] = [
            self::PUBLISHED => "fas fa-book",
            self::DRAFT     => "fas fa-drafting-compass",
            self::FUTURE    => "fas fa-stopwatch",
            self::SECRET    => "fas fa-eye-slash",
            self::ARCHIVED  => "fas fa-archive",
            
            self::APPROVED  => "fas fa-check-circle",
            self::SUSPENDED => "fas fa-exclamation-circle",
            self::PENDING   => "fas fa-pause-circle",
            self::REJECTED  => "fas fa-times-circle",
            self::DELETED   => "fas fa-trash-alt",
        ];

        return parent::getIcons($pos, ...$arrays);
    }
}