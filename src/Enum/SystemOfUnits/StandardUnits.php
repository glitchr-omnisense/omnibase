<?php

namespace Base\Enum\SystemOfUnits;

use Base\Service\Model\IconizeInterface;

/**
 *
 */
class StandardUnits extends StandardBaseUnits implements IconizeInterface
{
    // const ...   = "";

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return [];
    }
}
