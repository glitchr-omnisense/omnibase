<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;

/**
 *
 */
class ConnectionState extends EnumType implements IconizeInterface
{
    public const FAILED = "CONNECTION_FAILED";
    public const REQUESTED = "CONNECTION_REQUESTED";
    public const SUCCEEDED = "CONNECTION_SUCCEEDED";
    public const CLOSED = "CONNECTION_CLOSED";

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return [
            self::FAILED => ["fa-regular fa-circle-xmark"],
            self::REQUESTED => ["fa-solid fa-hourglass-half"],
            self::SUCCEEDED => ["fa-regular fa-square-check"],
            self::CLOSED => ["fa-solid fa-arrow-right-from-bracket"]
        ];
    }
}
