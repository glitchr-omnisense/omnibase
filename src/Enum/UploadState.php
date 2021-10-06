<?php

namespace Base\Enum;

use Base\Database\Types\SetType;

class UploadState extends SetType
{
    const UPLOAD_ERROR     = "UPLOAD_ERROR";

    const UPLOAD_FILESIZE  = "UPLOAD_FILESIZE";
    const UPLOAD_MIMETYPE  = "UPLOAD_MIMETYPE";
}