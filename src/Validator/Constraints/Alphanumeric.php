<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;

/**
 * @Annotation
 */
class Alphanumeric extends Constraint
{
    public $message = 'alphanumeric';
}