<?php

namespace Base\Enum;

use Base\Database\Type\SetType;
use Base\Model\IconizeInterface;

class UserRole extends SetType implements IconizeInterface
{
    const SOCIAL      = "ROLE_SOCIAL";
    const SUPERADMIN  = "ROLE_SUPERADMIN";
    const ADMIN       = "ROLE_ADMIN";
    const USER        = "ROLE_USER";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::SUPERADMIN => ["fas fa-cog", "fas fa-user-cog"],
            self::ADMIN      => ["fas fa-crown", "fas fa-star"],
            self::USER       => ["fas fa-user", "fas fa-user-tag"],
            self::SOCIAL     => ["fas fa-user-friends"],
        ];
    }
}