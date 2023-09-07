<?php

namespace Base\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

use Base\Validator\ConstraintEntityValidator;

/**
 *
 */
class StringCaseEntityValidator extends ConstraintEntityValidator
{
    /**
     * @param $value
     * @param Constraint $constraint
     * @return void
     * @throws \Exception
     */
    public function validate($value, Constraint $constraint): void
    {
        parent::validate($value, $constraint);

        $entity = $value;
        $originalEntity = $this->getOriginalEntity($entity);
        if (empty($originalEntity)) {
            throw new ConstraintDefinitionException(sprintf('The "%s" entity is not persistent yet. StringCase can only be used with persistent entities', get_class($entity)));
        }

        $entityChanges = $this->getEntityChangeSet($entity);

        $fields = (array)$constraint->fields;
        foreach ($fields as $fieldName) {
            if (!array_key_exists($fieldName, $entityChanges)) {
                continue;
            }

            $originalFieldValue = $entityChanges[$fieldName][0] ?? "";
            $fieldValue = $entityChanges[$fieldName][1] ?? "";

            if (mb_strtolower($fieldValue) != mb_strtolower($originalFieldValue)) {
                $this->buildViolation($constraint, $fieldValue)->addViolation();
            }
        }
    }
}
