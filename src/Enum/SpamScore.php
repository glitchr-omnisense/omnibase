<?php

namespace Base\Enum;

use Base\Database\Types\EnumType;

class SpamScore extends EnumType
{
    const NOT_SPAM     = 0;
    const MAYBE_SPAM   = 1;
    const BLATANT_SPAM = 2;
}