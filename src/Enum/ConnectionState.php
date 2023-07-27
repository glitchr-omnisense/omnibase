<?php

namespace Base\Enum;

use Base\Database\Type\SetType;
use Base\Service\Model\IconizeInterface;

/**
 *
 */
class ConnectionState extends SetType implements IconizeInterface
{
    public const FAILED = "CONNECTION_FAILED";
    public const SUCCEEDED = "CONNECTION_SUCCEEDED";
    public const LOGOUT = "CONNECTION_LOGOUT";

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return [
            self::FAILED => ["fa-regular fa-circle-xmark"],
            self::SUCCEEDED => ["fa-regular fa-square-check"],
            self::LOGOUT => ["fa-solid fa-arrow-right-from-bracket"]
        ];
    }
}
