<?php

namespace Base\Enum;

use Base\Database\Types\SetType;

class UserRole extends SetType
{
    const SUPERADMIN  = "ROLE_SUPERADMIN";
    const ADMIN       = "ROLE_ADMIN";
    const USER        = "ROLE_USER";
    const SOCIAL      = "ROLE_SOCIAL";

    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        $arrays[] = [
            self::SUPERADMIN => ["fas fa-star", "fas fa-user-cog"],
            self::ADMIN => ["fas fa-crown", "fas fa-user-check"],
            self::USER => ["fas fa-user", "fas fa-user-tag"],
            self::SOCIAL => ["fas fa-user-friends"],
        ];

        return parent::getIcons($pos, ...$arrays);
    }
}