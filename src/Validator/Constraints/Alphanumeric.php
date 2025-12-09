<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraint;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */

#[\Attribute]
class Alphanumeric extends Constraint
{
    public string $message = 'alphanumeric';
}
