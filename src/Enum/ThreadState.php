<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;
use Base\Model\IconizeInterface;

class ThreadState extends EnumType implements IconizeInterface
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
            self::PUBLISH  => ["fas fa-book"],
            self::DRAFT    => ["fas fa-drafting-compass"],
            self::FUTURE   => ["fas fa-stopwatch"],
            self::SECRET   => ["fas fa-eye-slash"],
            self::ARCHIVE  => ["fas fa-archive"],
            self::PASSWORD => ["fas fa-key"],
            self::DELETE   => ["fas fa-trash-alt"],
        ];
    }
}