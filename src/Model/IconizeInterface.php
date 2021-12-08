<?php

namespace Base\Model;

interface IconizeInterface
{
    public function __iconize(): ?array;
    public static function __staticIconize(): ?array;
}
