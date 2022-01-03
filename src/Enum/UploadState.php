<?php

namespace Base\Enum;

use Base\Database\Type\SetType;

class UploadState extends SetType
{
    const UPLOAD_ERROR     = "UPLOAD_ERROR";

    const UPLOAD_FILESIZE  = "UPLOAD_FILESIZE";
    const UPLOAD_MIMETYPE  = "UPLOAD_MIMETYPE";

    public static function getIcons(int $pos = -1, ...$arrays): array
    {
        $arrays[] = [
            self::UPLOAD_ERROR => "fas fa-exclamation-triangle",
            self::UPLOAD_FILESIZE => "fab fa-mixer",
            self::UPLOAD_MIMETYPE => "fas fa-filter"
        ];

        return parent::getIcons($pos, ...$arrays);
    }
}