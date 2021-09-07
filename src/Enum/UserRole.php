<?php

namespace Base\Enum;

use Base\Database\Types\EnumType;

class UserRole extends EnumType
{
    const SUPERADMIN  = "ROLE_SUPERADMIN";
    const ADMIN       = "ROLE_ADMIN";
    const USER        = "ROLE_USER";

    const SOCIAL      = "ROLE_SOCIAL";
}