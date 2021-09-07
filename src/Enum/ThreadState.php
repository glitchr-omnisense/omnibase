<?php

namespace Base\Enum;

use Base\Database\Types\EnumType;

class ThreadState extends EnumType
{
    const APPROVED  = "STATE_APPROVED";
    const PENDING   = "STATE_PENDING";
    const REJECTED  = "STATE_REJECTED";
    const DELETED   = "STATE_DELETED";

    const SECRET    = "STATE_SECRET";
    const DRAFT     = "STATE_DRAFT";
    const FUTURE    = "STATE_FUTURE";
    const PUBLISHED = "STATE_PUBLISHED";
}