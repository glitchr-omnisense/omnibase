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
    public static function __iconizeStatic(): ?array
    {
        return [
            self::MALE       => ["fas fa-fw fa-mars"],
            self::FEMALE     => ["fas fa-fw fa-venus"],
            self::HYBRID     => ["fas fa-fw fa-mercury"],
            self::GENDERLESS => ["fas fa-fw fa-genderless"]
        ];
    }
}