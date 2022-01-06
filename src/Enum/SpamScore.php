<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Model\IconizeInterface;

class SpamScore extends EnumType implements IconizeInterface
{
    const NO_TEXT      = -1;
    const NOT_SPAM     =  0;
    const MAYBE_SPAM   =  1;
    const BLATANT_SPAM =  2;

    public function __iconize(): ?array { return null; }
    public static function __staticIconize(): ?array
    {
        return [
            self::NO_TEXT      => ["fas fa-file"],
            self::NOT_SPAM     => ["fas fa-file-alt"],
            self::MAYBE_SPAM   => ["fas fa-question-circle"],
            self::BLATANT_SPAM => ["fas fa-exclamation-circle"],
        ];
    }
}
