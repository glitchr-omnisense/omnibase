<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;

class Operation extends EnumType implements IconizeInterface
{
    public const LT  = "LESS";
    public const LTE = "LESS_EQUAL";
    public const EQ  = "EQUAL";
    public const GTE = "GREATER_EQUAL";
    public const GT  = "GREATER";

    public const NEQ = "NOTEQUAL";

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return [
            self::LT  => ["fa-solid fa-less-than"],
            self::LTE => ["fa-solid fa-less-than-equal"],
            self::EQ  => ["fa-solid fa-equals"],
            self::GTE => ["fa-solid fa-greater-than-equal"],
            self::GT  => ["fa-solid fa-greater-than"],

            self::NEQ => ["fa-solid fa-not-equal"]
        ];
    }
}
