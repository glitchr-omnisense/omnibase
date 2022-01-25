<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Model\IconizeInterface;

class SpamScore extends EnumType implements IconizeInterface
{
    const NO_TEXT      = "NO_TEXT";
    const NOT_SPAM     = "NOT_SPAM";
    const MAYBE_SPAM   = "MAYBE_SPAM";
    const BLATANT_SPAM = "BLATANT_SPAM";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::NO_TEXT      => ["fas fa-file"],
            self::NOT_SPAM     => ["fas fa-file-alt"],
            self::MAYBE_SPAM   => ["fas fa-question-circle"],
            self::BLATANT_SPAM => ["fas fa-exclamation-circle"],
        ];
    }

    public static function __toInt()
    {
        return [
            self::NO_TEXT      => -1,
            self::NOT_SPAM     =>  0,
            self::MAYBE_SPAM   =>  1,
            self::BLATANT_SPAM =>  2
        ];
    }
}
