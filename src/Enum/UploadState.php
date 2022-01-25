<?php

namespace Base\Enum;

use Base\Database\Type\SetType;
use Base\Model\IconizeInterface;

class UploadState extends SetType implements IconizeInterface
{
    const UPLOAD_ERROR     = "UPLOAD_ERROR";
    const UPLOAD_FILESIZE  = "UPLOAD_FILESIZE";
    const UPLOAD_MIMETYPE  = "UPLOAD_MIMETYPE";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::UPLOAD_ERROR => ["fas fa-exclamation-triangle"],

            self::UPLOAD_FILESIZE => ["fab fa-mixer"],
            self::UPLOAD_MIMETYPE => ["fas fa-filter"]
        ];
    }
}