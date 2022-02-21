<?php

namespace Base\Enum;

use Base\Database\Type\EnumType;

class EntityAction extends EnumType
{
    const INSERT = "ACTION_INSERT";
    const UPDATE = "ACTION_UPDATE";
    const DELETE = "ACTION_DELETE";
}
