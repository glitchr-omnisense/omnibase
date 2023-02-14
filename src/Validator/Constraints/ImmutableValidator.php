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

use Symfony\Component\Validator\Constraint;
use Base\Validator\ConstraintEntityValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ImmutableValidator extends ConstraintEntityValidator
{
    /**
     * @param object $entity
     *
     * @throws UnexpectedTypeException
     * @throws ConstraintDefinitionException
     */
    public function validate($entity, Constraint $constraint)
    {
        parent::validate($entity, $constraint);

        $em = $constraint->em ?? $this->getEntityManager(\get_class($entity));
        if (!$em)
            throw new ConstraintDefinitionException(sprintf('Unable to find the object manager associated with an entity of class "%s".', get_debug_type($entity)));

        $classMetadata = $this->getClassMetadata($entity);

        $criteria = [];
        $hasNullValue = false;

        $fields = array_map(fn($f) => $classMetadata->getFieldName($f), $constraint->fields);
        foreach ($fields as $key => $fieldName) {

            //
            // Property path
            $fieldPath = explode(".", $fieldName);
            if(count($fieldPath) > 1) {

                $fieldName = head($fieldPath);
                if(!$classMetadata->hasAssociation($fieldName)) {
                    throw new ConstraintDefinitionException(sprintf('The field "%s" is expected to be an association.', $fieldName));
                }

                foreach($classMetadata->getFieldValue($entity, $fieldName) as $association) {

                    $constraint->fields[$key] = implode(".", tail($fieldPath));
                    $constraint->message = implode(".", tail($fieldPath));

                    $this->validate($association, $constraint);
                }

                return;
            }
        }

        $oldEntity = $this->getOriginalEntity($entity);
        if (!$oldEntity || $classMetadata->getFieldValue($oldEntity, $fieldName) == $classMetadata->getFieldValue($entity, $fieldName)) {
            return;
        }

        if($constraint instanceof \Base\Validator\ConstraintEntity)
            $constraint->entity  = $entity;

        $errorPath = null !== $constraint->errorPath ? $constraint->errorPath : $fields[0];
        $invalidValue = $criteria[$errorPath] ?? $criteria[$fields[0]];

        $this->buildViolation($constraint, $invalidValue)->addViolation();
    }
}
