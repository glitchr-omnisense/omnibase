<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;

class EntityAction extends EnumType implements IconizeInterface
{
    public const INSERT = "ACTION_INSERT";
    public const UPDATE = "ACTION_UPDATE";
    public const DELETE = "ACTION_DELETE";

    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::INSERT => ["fa-solid fa-calendar-plus"],
            self::UPDATE => ["fa-solid fa-calendar"],
            self::DELETE => ["fa-solid fa-calendar-times"]
        ];
    }
}
