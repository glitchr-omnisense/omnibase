<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;

#[\Attribute]
class AlphanumericPlus extends Constraint
{
    public string $message = 'alphanumeric_plus';
}
