<?php

namespace Base\Enum;

use Base\Database\Types\EnumType;

class LogLevel extends EnumType
{
    const INFO     = "INFO";
    const DEBUG    = "DEBUG";
    const WARNING  = "WARNING";
    const CRITICAL = "CRITICAL";
}
