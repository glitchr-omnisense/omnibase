<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraints\Password;
use Base\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PasswordValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!$constraint instanceof Password) {
            throw new UnexpectedTypeException($constraint, Password::class);
        }

        $minLength = $constraint->getMinLength();
        $minStrength = $constraint->getMinStrength();

        // Check length of password
        if (strlen($value) < $minLength) {
            $constraint->message = $constraint->messageMinLength;
            $this->buildViolation($constraint, $value)
                ->setParameter('{0}', strlen($value))
                ->addViolation();
        }

        // Check strength of password (if necessary)
        $strength = 0;
        if ($minStrength > 0) {
            if ($strength < $minStrength) {
                $strength += (int)preg_match('/[a-z]+/', $value);
            } // lowercase
            if ($strength < $minStrength) {
                $strength += (int)preg_match('/[A-Z]+/', $value);
            } // uppercase
            if ($strength < $minStrength) {
                $strength += (int)preg_match('/[0-9]+/', $value);
            } // numbers
            if ($strength < $minStrength) {
                $strength += (int)preg_match('/[\W]+/', $value);
            } // specials
            if ($strength < $minStrength) {
                $strength += strlen($value) > 12;
            } // length
        }

        if ($strength < $minStrength) {
            $constraint->message = $constraint->messageMinStrength;
            $this->buildViolation($constraint, $value)
                ->setParameter('{0}', $strength)
                ->addViolation();
        }
    }
}
