<?php

namespace Base\Validator;

use Base\Traits\BaseTrait;
use Exception;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;

/**
 * @Annotation
 */
abstract class ConstraintValidator extends \Symfony\Component\Validator\ConstraintValidator
{
    use BaseTrait;
    
    public string $constraintClass;

    public function __construct()
    {
        $this->translator = $this->getTranslator();
        $this->constraintClass = preg_replace('/Validator$/', '', get_called_class());
    }

    public function setParameter(string $parameterName, ?string $parameterValue = null)
    {
        if($this->buildViolation == null)
            throw new UnexpectedValueException("Please build violation before calling ".self::class."::setParameter.");

        $this->buildViolation
            ->setParameter("{{ ".$parameterName." }}", $parameterValue ?? "")
            ->setParameter("{ ".$parameterName." }", $parameterValue ?? "");

        return $this;
    }

    public function getPropertyName()
    {
        return str_lstrip($this->context->getPropertyPath(), "data.");
    }
    public function getConstraintType()
    {
        return empty($this->getPropertyName()) ? "class" : "property";
    }

    protected $buildViolation = null;
    public function buildViolation(Constraint $constraint, $value = null): ConstraintViolationBuilderInterface {

        $value = is_stringeable($value) ? $value : "";

        $this->buildViolation = $this->context->buildViolation($constraint->message);
        $this->setParameter('field', $this->getPropertyName());
        $this->setParameter('value', $value);

        return $this->buildViolation
            ->setInvalidValue($value)
            ->setTranslationDomain('validators');
    }
}