<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;

#[\Attribute]
class Hexcode extends Constraint
{
    public string $message = 'hexcode';
}
