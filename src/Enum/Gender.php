<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Model\IconizeInterface;

class Gender extends EnumType implements IconizeInterface
{
    const MALE       = "MALE";
    const FEMALE     = "FEMALE";
    const HYBRID     = "HYBRID";
    const GENDERLESS = "GENDERLESS";
    
    public function __iconize(): ?array { return null; }
    public static function __staticIconize(): ?array
    {
        return [
            self::MALE       => "fa fa-fw fa-mars",
            self::FEMALE     => "fa fa-fw fa-venus",
            self::HYBRID     => "fa fa-fw fa-mercury",
            self::GENDERLESS => "fa fa-fw fa-genderless"
        ];
    }
}