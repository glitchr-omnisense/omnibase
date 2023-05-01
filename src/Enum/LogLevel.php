<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;

/**
 *
 */
class LogLevel extends EnumType implements IconizeInterface
{
    public const INFO = "INFO";
    public const DEBUG = "DEBUG";
    public const WARNING = "WARNING";
    public const CRITICAL = "CRITICAL";

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return [
            self::INFO => ["fa-solid fa-info-circle", "fa-solid fa-info-triangle", "fa-solid fa-info"],
            self::DEBUG => ["fa-solid fa-question-circle", "fa-solid fa-question-triangle", "fa-solid fa-question"],
            self::WARNING => ["fa-solid fa-exclamation-circle", "fa-solid fa-exclamation-triangle", "fa-solid fa-exclamation"],
            self::CRITICAL => ["fa-solid fa-skull-crossbones"],
        ];
    }
}
