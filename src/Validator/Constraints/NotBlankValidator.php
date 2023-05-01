<?php

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintValidator;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use function is_string;

class NotBlankValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof NotBlank) {
            throw new UnexpectedTypeException($constraint, NotBlank::class);
        }

        if ($constraint->allowNull && null === $value) {
            return;
        }

        if (is_string($value) && null !== $constraint->normalizer) {
            $value = ($constraint->normalizer)($value);
        }

        if (false === $value || (empty($value) && '0' != $value) || ($value instanceof PersistentCollection && empty($value->toArray()))) {
            $this->buildViolation($constraint, $value)->addViolation();
        }
    }
}
