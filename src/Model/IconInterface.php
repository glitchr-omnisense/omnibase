<?php

namespace Base\Model;

interface IconInterface
{
    public static function getIcons(int $pos, ...$arrays): array;
}
