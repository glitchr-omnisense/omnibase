<?php

namespace Base\Enum;

use Base\Database\Type\SetType;
use Base\Service\Model\IconizeInterface;

class UserState extends SetType implements IconizeInterface
{
    public const NEWCOMER = "USER_NEWCOMER";
    public const GHOST    = "USER_GHOST";
    public const BANNED   = "USER_BANNED";
    public const LOCKED   = "USER_LOCKED";
    public const KICKED   = "USER_KICKED";
    public const VERIFIED = "USER_VERIFIED";
    public const APPROVED = "USER_APPROVED";
    public const ENABLED  = "USER_ENABLED";

    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::NEWCOMER => ["fa-solid fa-user-plus"],
            self::GHOST    => ["fa-solid fa-user-ghost"],
            self::BANNED   => ["fa-solid fa-user-slash"],
            self::LOCKED   => ["fa-solid fa-user-lock"],
            self::KICKED   => ["fa-solid fa-user-times"],
            self::VERIFIED => ["fa-solid fa-user-check"],
            self::APPROVED => ["fa-solid fa-user-shield"],
            self::ENABLED  => ["fa-solid fa-user"],
        ];
    }
}
