<?php

namespace Base\Enum;

use Base\Database\Type\SetType;
use Base\Service\Model\IconizeInterface;

class UserRole extends SetType implements IconizeInterface
{
    const EDITOR      = "ROLE_EDITOR";

    const SUPERADMIN  = "ROLE_SUPERADMIN";
    const ADMIN       = "ROLE_ADMIN";
    const USER        = "ROLE_USER";

    const SOCIAL      = "ROLE_SOCIAL";
    
    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::EDITOR   => ["fas fa-hard-hat", "fas fa-hard-hat"],
        
            self::SUPERADMIN => ["fas fa-cog", "fas fa-user-cog"],
            self::ADMIN      => ["fas fa-crown", "fas fa-star"],
            self::USER       => ["fas fa-user", "fas fa-user-tag"],

            self::SOCIAL     => ["fas fa-user-friends"],
        ];
    }
}