<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;

class SpamApi extends EnumType implements IconizeInterface
{
    public const AKISMET      = "AKISMET";

    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::AKISMET => ["fa-solid fa-backspace"]
        ];
    }
}
