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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

use Base\Validator\ConstraintEntityValidator;

class StringCaseEntityValidator extends ConstraintEntityValidator
{
    public function validate($entity, Constraint $constraint)
    {
        if(parent::validate($entity, $constraint)) return;

        $originalEntity = $this->getOriginalEntity($entity);
        if(empty($originalEntity))
            throw new ConstraintDefinitionException(sprintf('The "%s" entity is not persistent yet. StringCase can only be used with persistent entities', get_class($entity)));

        $entityChanges = $this->getEntityChangeSet($entity);

        $fields = (array) $constraint->fields;
        foreach ($fields as $fieldName) {

            if( !array_key_exists($fieldName, $entityChanges) )
                continue;

            $originalFieldValue = $entityChanges[$fieldName][0] ?? "";
            $fieldValue = $entityChanges[$fieldName][1] ?? "";

            if(mb_strtolower($fieldValue) != mb_strtolower($originalFieldValue))
                $this->buildViolation($constraint, $fieldValue)->addViolation();
        }
    }
}
