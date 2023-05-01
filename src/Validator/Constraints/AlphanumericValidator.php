<?php

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class AlphanumericValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Alphanumeric) {
            throw new UnexpectedTypeException($constraint, Alphanumeric::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value, $matches)) {
            $this->buildViolation($constraint, $value)->addViolation();
        }
    }
}
