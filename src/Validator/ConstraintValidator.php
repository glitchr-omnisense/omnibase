<?php

namespace Base\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @Annotation
 */
abstract class ConstraintValidator extends \Symfony\Component\Validator\ConstraintValidator
{
    public string $constraintClass;

    public function __construct()
    {
        $this->constraintClass = preg_replace('/Validator$/', '', get_called_class());
    }

    public function buildViolation($value, Constraint $constraint): ConstraintViolationBuilderInterface {

        $buildViolation = $this->context
            ->buildViolation($constraint->message);

        $buildViolation
            ->setParameter('{{ value }}', $value)->setParameter('{value}', $value)
            ->setTranslationDomain('validators')
            ->addViolation();

        return $buildViolation;
    }
}