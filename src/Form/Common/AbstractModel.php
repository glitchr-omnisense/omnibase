<?php

namespace Base\Form\Common;

class AbstractModel implements FormModelInterface
{
    public static function getTypeClass(): string
    {
        return str_replace("Model", "Type", static::class);
    }
}
