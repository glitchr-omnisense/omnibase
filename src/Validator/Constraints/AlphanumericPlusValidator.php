<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraints\AlphanumericPlus;
use Base\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class AlphanumericPlusValidator extends ConstraintValidator
{
    public function validate($entry, Constraint $constraint)
    {
        if (!$constraint instanceof AlphanumericPlus) {
            throw new UnexpectedTypeException($constraint, AlphanumericPlus::class);
        }

        if (null === $entry || '' === $entry) {
            return;
        }

        if (!is_string($entry)) {
            throw new UnexpectedValueException($entry, 'string');
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $entry, $matches)) {
            $this->buildViolation($constraint, $entry)->addViolation();
        }
    }
}
