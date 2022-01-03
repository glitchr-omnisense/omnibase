<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;

class LogLevel extends EnumType
{
    const INFO     = "INFO";
    const DEBUG    = "DEBUG";
    const WARNING  = "WARNING";
    const CRITICAL = "CRITICAL";

    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        $arrays[] = [
            self::INFO     => ["fas fa-info-circle"       , "fas fa-info-triangle", "fas fa-info"              ],
            self::DEBUG    => ["fas fa-question-circle"   , "fas fa-question-triangle", "fas fa-question"      ],
            self::WARNING  => ["fas fa-exclamation-circle", "fas fa-exclamation-triangle", "fas fa-exclamation"],
            self::CRITICAL => ["fas fa-skull-crossbones"],
        ];

        return parent::getIcons($pos, ...$arrays);
    }
}