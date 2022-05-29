<?php

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintEntity;

/**
 * Constraint for the StringCase Entity validator.
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 *
 */
class StringCaseEntity extends ConstraintEntity
{
    public $message = 'string_case';
}
