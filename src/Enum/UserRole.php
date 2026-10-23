<?php

namespace Base\Enum;

use Base\Database\Type\SetType;
use Base\Service\Model\IconizeInterface;

class UserRole extends SetType implements IconizeInterface
{
    public const EDITOR = "ROLE_EDITOR";

    public const SUPERADMIN = "ROLE_SUPERADMIN";
    public const ADMIN = "ROLE_ADMIN";
    public const USER = "ROLE_USER";

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return [
            self::EDITOR => ["fa-solid fa-hard-hat", "fa-solid fa-hard-hat"],

            self::SUPERADMIN => ["fa-solid fa-cog", "fa-solid fa-user-cog"],
            self::ADMIN => ["fa-solid fa-crown", "fa-solid fa-star"],
            self::USER => ["fa-solid fa-user", "fa-solid fa-user-tag"],
        ];
    }

    /** The back office's screen of the accounts of that role: omnibase/admin's, when it is installed. */
    public static function getCrudController(string $role): ?string
    {
        return match ($role) {
            self::EDITOR, self::SUPERADMIN, self::ADMIN, self::USER => 'Base\Admin\Controller\Crud\UserCrudController',
            default => null
        };
    }
}
