<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;

class UserGender extends EnumType implements IconizeInterface
{
    public const MALE       = "MALE";
    public const FEMALE     = "FEMALE";
    public const HYBRID     = "HYBRID";
    public const GENDERLESS = "GENDERLESS";

    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::MALE       => ["fa-solid fa-fw fa-mars"],
            self::FEMALE     => ["fa-solid fa-fw fa-venus"],
            self::HYBRID     => ["fa-solid fa-fw fa-mercury"],
            self::GENDERLESS => ["fa-solid fa-fw fa-genderless"]
        ];
    }
}
