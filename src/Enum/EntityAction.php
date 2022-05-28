<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Model\IconizeInterface;

class EntityAction extends EnumType implements IconizeInterface
{
    const INSERT = "ACTION_INSERT";
    const UPDATE = "ACTION_UPDATE";
    const DELETE = "ACTION_DELETE";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::INSERT => ["fas fa-calendar-plus"],
            self::UPDATE => ["fas fa-calendar"],
            self::DELETE => ["fas fa-calendar-times"]
        ];
    }
}
