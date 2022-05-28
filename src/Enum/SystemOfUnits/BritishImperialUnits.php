<?php

namespace Base\Enum\SystemOfUnits;

use Base\Database\Type\EnumType;
use Base\Model\IconizeInterface;

class BritishImperialUnits extends EnumType implements IconizeInterface
{
    // const ...   = "";
    
    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [];
    }
}