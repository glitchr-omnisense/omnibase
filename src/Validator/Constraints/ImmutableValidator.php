<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Base\Validator\Constraints;

use Base\Validator\ConstraintEntity;
use Symfony\Component\Validator\Constraint;
use Base\Validator\ConstraintEntityValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use function get_class;

class ImmutableValidator extends ConstraintEntityValidator
{
    /**
     * @param object $entity
     *
     * @throws UnexpectedTypeException
     * @throws ConstraintDefinitionException
     */
    public function validate($value, Constraint $constraint)
    {
        parent::validate($value, $constraint);

        $em = $constraint->em ?? $this->getEntityManager(get_class($value));
        if (!$em) {
            throw new ConstraintDefinitionException(sprintf('Unable to find the object manager associated with an entity of class "%s".', get_debug_type($value)));
        }

        $classMetadata = $this->getClassMetadata($value);

        $criteria = [];
        $hasNullValue = false;

        $fields = array_map(fn($f) => $classMetadata->getFieldName($f), $constraint->fields);
        foreach ($fields as $key => $fieldName) {
            //
            // Property path
            $fieldPath = explode(".", $fieldName);
            if (count($fieldPath) > 1) {
                $fieldName = head($fieldPath);
                if (!$classMetadata->hasAssociation($fieldName)) {
                    throw new ConstraintDefinitionException(sprintf('The field "%s" is expected to be an association.', $fieldName));
                }

                foreach ($classMetadata->getFieldValue($value, $fieldName) as $association) {
                    $constraint->fields[$key] = implode(".", tail($fieldPath));
                    $constraint->message = implode(".", tail($fieldPath));

                    $this->validate($association, $constraint);
                }

                return;
            }
        }

        $oldEntity = $this->getOriginalEntity($value);
        if (!$oldEntity || $classMetadata->getFieldValue($oldEntity, $fieldName) == $classMetadata->getFieldValue($value, $fieldName)) {
            return;
        }

        if ($constraint instanceof ConstraintEntity) {
            $constraint->entity = $value;
        }

        $errorPath = null !== $constraint->errorPath ? $constraint->errorPath : $fields[0];
        $invalidValue = $errorPath ?? $fields[0] ?? null;

        $this->buildViolation($constraint, $invalidValue)->addViolation();
    }
}
