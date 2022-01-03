<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;

class Gender extends EnumType
{
    const MALE       = "MALE";
    const FEMALE     = "FEMALE";
    const HYBRID     = "HYBRID";
    const GENDERLESS = "GENDERLESS";
    
    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        $arrays[] = [
            self::MALE       => "fa fa-fw fa-mars",
            self::FEMALE     => "fa fa-fw fa-venus",
            self::HYBRID     => "fa fa-fw fa-mercury",
            self::GENDERLESS => "fa fa-fw fa-genderless"
        ];

        return parent::getIcons($pos, ...$arrays);
    }
}