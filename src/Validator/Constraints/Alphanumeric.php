<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;


#[\Attribute]
class Alphanumeric extends Constraint
{
    public string $message = 'alphanumeric';
}
