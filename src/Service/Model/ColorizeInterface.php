<?php

namespace Base\Service\Model;

/**
 *
 */
interface ColorizeInterface
{
    public function __colorize(): ?array;

    public static function __colorizeStatic(): ?array;
}
