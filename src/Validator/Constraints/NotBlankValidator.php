<?php


namespace Base\Validator\Constraints;

use Base\Entity\User\Notification;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NotBlankValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($object, Constraint $constraint)
    {
        if (!$constraint instanceof NotBlank) {
            throw new UnexpectedTypeException($constraint, NotBlank::class);
        }

        if ($constraint->allowNull && null === $object) {
            return;
        }

        if (\is_string($object) && null !== $constraint->normalizer) {
            $object = ($constraint->normalizer)($object);
        }
        
        if (false === $object || (empty($object) && '0' != $object) || ($object instanceof \Doctrine\ORM\PersistentCollection && empty($object->toArray()))) {

            $this->context->buildViolation($constraint->message . ".property")
                ->setParameter('{label}', $this->formatValue($object))
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }
}
