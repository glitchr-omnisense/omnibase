<?php

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintEntity;

/**
 * Constraint for the StringCase Entity validator.
 */

#[\Attribute(\Attribute::TARGET_CLASS)]
class StringCaseEntity extends ConstraintEntity
{
    public ?string $message = 'string_case';
}
