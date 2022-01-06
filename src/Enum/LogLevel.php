<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Model\IconizeInterface;

class LogLevel extends EnumType implements IconizeInterface
{
    const INFO     = "INFO";
    const DEBUG    = "DEBUG";
    const WARNING  = "WARNING";
    const CRITICAL = "CRITICAL";

    public function __iconize(): ?array { return null; }
    public static function __staticIconize(): ?array
    {
        return [
            self::INFO     => ["fas fa-info-circle"       , "fas fa-info-triangle", "fas fa-info"              ],
            self::DEBUG    => ["fas fa-question-circle"   , "fas fa-question-triangle", "fas fa-question"      ],
            self::WARNING  => ["fas fa-exclamation-circle", "fas fa-exclamation-triangle", "fas fa-exclamation"],
            self::CRITICAL => ["fas fa-skull-crossbones"],
        ];
    }
}