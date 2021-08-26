<?php

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintEntity;

/**
 * Constraint for the StringCase Entity validator.
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 *
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 */
class StringCaseEntity extends ConstraintEntity
{
    public $message = 'stringCase';
}
