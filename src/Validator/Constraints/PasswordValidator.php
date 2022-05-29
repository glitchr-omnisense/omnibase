<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraints\Password;
use Base\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PasswordValidator extends ConstraintValidator
{
    public function validate($entry, Constraint $constraint)
    {
        if (null === $entry || '' === $entry)
            return;

        if (!$constraint instanceof Password)
            throw new UnexpectedTypeException($constraint, Password::class);

        $minLength   = $constraint->getMinLength();
        $minStrength = $constraint->getMinStrength();

        // Check length of password
        if(strlen($entry) < $minLength) {

            $constraint->message = $constraint->messageMinLength;
            $this->buildViolation($constraint, $entry)
                ->setParameter('{0}', strlen($entry))
                ->addViolation();
        }

        // Check strength of password (if necessary)
        $strength = 0;
        if($minStrength > 0) {
            if($strength < $minStrength) $strength += (int) preg_match('/[a-z]+/', $entry); // lowercase
            if($strength < $minStrength) $strength += (int) preg_match('/[A-Z]+/', $entry); // uppercase
            if($strength < $minStrength) $strength += (int) preg_match('/[0-9]+/', $entry); // numbers
            if($strength < $minStrength) $strength += (int) preg_match('/[\W]+/' , $entry); // specials
            if($strength < $minStrength) $strength += (int) strlen($entry) > 12; // length12
        }

        if ($strength < $minStrength) {

            $constraint->message = $constraint->messageMinStrength;
            $this->buildViolation($constraint, $entry)
                ->setParameter('{0}', $strength)
                ->addViolation();
        }
    }
}
