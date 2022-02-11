<?php

namespace Base\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Hexcode extends Constraint
{
    public $message = 'The color {{ value }} is not a valid hexadecimal value.';
}