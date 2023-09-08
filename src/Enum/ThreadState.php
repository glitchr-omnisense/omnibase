<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\ColorizeInterface;
use Base\Service\Model\IconizeInterface;

/**
 *
 */
class ThreadState extends EnumType implements IconizeInterface, ColorizeInterface
{
    public const DRAFT = "STATE_DRAFT";
    public const FUTURE = "STATE_FUTURE";
    public const PUBLISH = "STATE_PUBLISH";
    public const SECRET = "STATE_PUBLISH_SECRET";
    public const ARCHIVE = "STATE_PUBLISH_ARCHIVE";
    public const PASSWORD = "STATE_PUBLISH_PASSWORD";
    public const DELETE = "STATE_DELETE";

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return [
            self::PUBLISH => ["fa-solid fa-book", "fa-solid fa-check"],
            self::DRAFT => ["fa-solid fa-drafting-compass", "fa-solid fa-exclamation"],
            self::FUTURE => ["fa-solid fa-stopwatch", "fa-solid fa-hourglass-half"],
            self::SECRET => ["fa-solid fa-eye-slash", "fa-solid fa-check"],
            self::ARCHIVE => ["fa-solid fa-archive", "fa-solid fa-check"],
            self::PASSWORD => ["fa-solid fa-key", "fa-solid fa-check"],
            self::DELETE => ["fa-solid fa-trash-alt", "fa-solid fa-exclamation"],
        ];
    }

    public function __colorize(): ?array
    {
        return null;
    }

    public static function __colorizeStatic(): ?array
    {
        return [
            self::PUBLISH => ["#198754"],
            self::DRAFT => ["#b02a37"],
            self::FUTURE => ["#97ccdd"],
            self::SECRET => ["#e0a800"],
            self::ARCHIVE => ["#198754"],
            self::PASSWORD => ["#e0a800"],
            self::DELETE => ["#b02a37"],
        ];
    }
}
