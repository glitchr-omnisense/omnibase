<?php

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraint;

class HexcodeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!empty(trim($value)) && 1 !== preg_match("/^#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})$/", $value)) {
            $this->buildViolation($constraint, $value)->addViolation();
        }
    }
}
