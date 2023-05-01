<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;

/**
 *
 */
class SpamScore extends EnumType implements IconizeInterface
{
    public const NO_TEXT = "NO_TEXT";
    public const NOT_SPAM = "NOT_SPAM";
    public const MAYBE_SPAM = "MAYBE_SPAM";
    public const BLATANT_SPAM = "BLATANT_SPAM";

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return [
            self::NO_TEXT => ["fa-solid fa-file"],
            self::NOT_SPAM => ["fa-solid fa-file-alt"],
            self::MAYBE_SPAM => ["fa-solid fa-question-circle"],
            self::BLATANT_SPAM => ["fa-solid fa-exclamation-circle"],
        ];
    }

    /**
     * @return int[]
     */
    public static function __toInt()
    {
        return [
            self::NO_TEXT => -1,
            self::NOT_SPAM => 0,
            self::MAYBE_SPAM => 1,
            self::BLATANT_SPAM => 2
        ];
    }
}
