<?php

namespace Base\Enum;

use Base\Controller\Backend\Crud\UserCrudController;
use Base\Database\Type\SetType;
use Base\Service\Model\IconizeInterface;
use Exception;

class UserRole extends SetType implements IconizeInterface
{
    public const EDITOR      = "ROLE_EDITOR";

    public const SUPERADMIN  = "ROLE_SUPERADMIN";
    public const ADMIN       = "ROLE_ADMIN";
    public const USER        = "ROLE_USER";

    public const SOCIAL      = "ROLE_SOCIAL";

    public function __iconize(): ?array
    {
        return null;
    }
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

    public static function getCrudController(string $role): ?string
    {
        switch($role) {
            case self::EDITOR:
            case self::SUPERADMIN:
            case self::ADMIN:
            case self::USER:
                return UserCrudController::class;
            case self::SOCIAL:
                return null;
        }
    }
}
