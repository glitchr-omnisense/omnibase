<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Service\Model\ColorizeInterface;
use Base\Service\Model\IconizeInterface;

class ThreadState extends EnumType implements IconizeInterface, ColorizeInterface
{
    const DRAFT    = "STATE_DRAFT";
    const FUTURE   = "STATE_FUTURE";
    const PUBLISH  = "STATE_PUBLISH";
    const SECRET   = "STATE_PUBLISH_SECRET";
    const ARCHIVE  = "STATE_PUBLISH_ARCHIVE";
    const PASSWORD = "STATE_PUBLISH_PASSWORD";
    const DELETE   = "STATE_DELETE";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::PUBLISH  => ["fas fa-book", "fas fa-check"],
            self::DRAFT    => ["fas fa-drafting-compass", "fas fa-exclamation"],
            self::FUTURE   => ["fas fa-stopwatch", "fas fa-hourglass-half"],
            self::SECRET   => ["fas fa-eye-slash", "fas fa-check"],
            self::ARCHIVE  => ["fas fa-archive", "fas fa-check"],
            self::PASSWORD => ["fas fa-key", "fas fa-check"],
            self::DELETE   => ["fas fa-trash-alt", "fas fa-exclamation"],
        ];
    }

    public function __colorize(): ?array { return null; }
    public static function __colorizeStatic(): ?array
    {
        return [
            self::PUBLISH  => ["#198754"],
            self::DRAFT    => ["#b02a37"],
            self::FUTURE   => ["#e0a800"],
            self::SECRET   => ["#e0a800"],
            self::ARCHIVE  => ["#198754"],
            self::PASSWORD => ["#e0a800"],
            self::DELETE   => ["#b02a37"],
        ];
    }
}