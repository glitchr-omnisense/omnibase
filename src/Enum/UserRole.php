<?php

namespace Base\Enum;

use Base\Controller\Backend\Crud\UserCrudController;
use Base\Database\Type\SetType;
use Base\Service\Model\IconizeInterface;

/**
 *
 */
class UserRole extends SetType implements IconizeInterface
{
    public const EDITOR = "ROLE_EDITOR";

    public const SUPERADMIN = "ROLE_SUPERADMIN";
    public const ADMIN = "ROLE_ADMIN";
    public const USER = "ROLE_USER";

    public const SOCIAL = "ROLE_SOCIAL";

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

            self::SOCIAL => ["fa-solid fa-user-friends"],
        ];
    }

    public static function getCrudController(string $role): ?string
    {
        return match ($role) {
            self::EDITOR, self::SUPERADMIN, self::ADMIN, self::USER => UserCrudController::class,
            default => null
        };
    }
}
