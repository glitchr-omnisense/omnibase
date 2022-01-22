<?php

namespace Base\Enum;

use Base\Database\Type\SetType;
use Base\Model\IconizeInterface;

class UserRole extends SetType implements IconizeInterface
{
    const SUPERADMIN  = "ROLE_SUPERADMIN";
    const ADMIN       = "ROLE_ADMIN";
    const USER        = "ROLE_USER";
    const SOCIAL      = "ROLE_SOCIAL";

    public function __iconize(): ?array { return null; }
    public static function __staticIconize(): ?array
    {
        return [
            self::SUPERADMIN => ["fas fa-cog", "fas fa-user-cog"],
            self::ADMIN => ["fas fa-crown", "fas fa-user-check"],
            self::USER => ["fas fa-user", "fas fa-user-tag"],
            self::SOCIAL => ["fas fa-user-friends"],
        ];
    }
}