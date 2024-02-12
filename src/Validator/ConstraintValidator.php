<?php

namespace Base\Validator;

use Base\Service\TranslatorInterface;
use Base\Traits\BaseTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use UnexpectedValueException;

abstract class ConstraintValidator extends \Symfony\Component\Validator\ConstraintValidator
{
    use BaseTrait;

    /**
     * @var string
     */
    public string $constraintClass;

    /**
     * @var ?TranslatorInterface
     */
    protected ?TranslatorInterface $translator;

    public function __construct()
    {
        $this->translator = $this->getTranslator();
        $this->constraintClass = preg_replace('/Validator$/', '', get_called_class());
    }

    /**
     * @param string $parameterName
     * @param string|null $parameterValue
     * @return $this
     */
    /**
     * @param string $parameterName
     * @param string|null $parameterValue
     * @return $this
     */
    public function setParameter(string $parameterName, ?string $parameterValue = null)
    {
        if ($this->buildViolation == null) {
            throw new UnexpectedValueException("Please build violation before calling " . self::class . "::setParameter.");
        }

        $this->buildViolation
            ->setParameter("{{ " . $parameterName . " }}", $parameterValue ?? "")
            ->setParameter("{ " . $parameterName . " }", $parameterValue ?? "");

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPropertyName()
    {
        return str_lstrip($this->context->getPropertyPath(), "data.");
    }

    /**
     * @return string
     */
    public function getConstraintType()
    {
        return empty($this->getPropertyName()) ? "class" : "property";
    }

    /**
     * @param Constraint $constraint
     * @return mixed
     */
    protected function formatIdentifier(Constraint $constraint)
    {
        return $constraint->message;
    }

    protected $buildViolation = null;

    /**
     * @param Constraint $constraint
     * @param $value
     * @return ConstraintViolationBuilderInterface
     */
    public function buildViolation(Constraint $constraint, $value = null): ConstraintViolationBuilderInterface
    {
        $this->buildViolation = $this->context->buildViolation($this->formatIdentifier($constraint));

        $this->setParameter('field', $this->getPropertyName());
        $this->setParameter('value', $value);

        return $this->buildViolation
            ->setInvalidValue($value)
            ->setTranslationDomain('validators');
    }
}
