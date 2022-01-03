<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;

class SpamApi extends EnumType
{
    const AKISMET      = "AKISMET";
    
    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        $arrays[] = [
            self::AKISMET => ["fas fa-backspace"],
        ];

        return parent::getIcons($pos, ...$arrays);
    }
}