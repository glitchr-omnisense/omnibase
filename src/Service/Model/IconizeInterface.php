<?php

namespace Base\Service\Model;

/**
 *
 */
interface IconizeInterface
{
    public function __iconize(): ?array;

    public static function __iconizeStatic(): ?array;
}
