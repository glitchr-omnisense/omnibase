<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;
use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */

#[\Attribute]
class Hexcode extends Constraint
{
    public $message = 'hexcode';
}
