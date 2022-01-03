<?php

namespace Base\Enum;

use Base\Database\Type\SetType;

class UserState extends SetType
{
    const NEWCOMER = "USER_NEWCOMER";
    const GHOST    = "USER_GHOST";
    const BANNED   = "USER_BANNED";
    const LOCKED   = "USER_LOCKED";
    const KICKED   = "USER_KICKED";
    const VERIFIED = "USER_VERIFIED";
    const APPROVED = "USER_APPROVED";
    const ENABLED  = "USER_ENABLED";

    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        $arrays[] = [
            self::NEWCOMER => ["fas fa-user-plus"],
            self::GHOST    => ["fas fa-user-ghost"],
            self::BANNED   => ["fas fa-user-slash"],
            self::LOCKED   => ["fas fa-user-lock"],
            self::KICKED   => ["fas fa-user-times"],
            self::VERIFIED => ["fas fa-user-check"],
            self::APPROVED => ["fas fa-user-shield"],
            self::ENABLED  => ["fas fa-user"],
        ];

        return parent::getIcons($pos, ...$arrays);
    }
}