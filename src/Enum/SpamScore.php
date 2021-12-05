<?php

namespace Base\Enum;

use Base\Database\Types\EnumType;

class SpamScore extends EnumType
{
    const NO_TEXT      = -1;
    const NOT_SPAM     =  0;
    const MAYBE_SPAM   =  1;
    const BLATANT_SPAM =  2;

    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        $arrays[] = [
            self::NO_TEXT      => ["fas fa-file"],
            self::NOT_SPAM     => ["fas fa-file-alt"],
            self::MAYBE_SPAM   => ["fas fa-question-circle"],
            self::BLATANT_SPAM => ["fas fa-exclamation-circle"],
        ];

        return parent::getIcons($pos, ...$arrays);
    }
}
