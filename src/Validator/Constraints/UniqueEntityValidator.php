<?php

namespace Base\Validator\Constraints;

use Base\Database\UnitOfWorks;
use Base\Validator\ConstraintEntity;
use Countable;
use Iterator;
use IteratorAggregate;
use Omnibridge\Persistence\Schedule;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Base\Validator\ConstraintEntityValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use function count;
use function get_class;
use function is_array;

/**
 * No other row with the same values of the fields, on any persistence: the repository of the entity's class (the host's
 * unit of work), and the entities scheduled for insertion in the same flush. The `em` option is no longer read (the
 * host's unit of work is the one).
 */
class UniqueEntityValidator extends ConstraintEntityValidator
{
    /**
     * @param $value
     * @param Constraint $constraint
     * @throws \Exception
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEntity) {
            throw new \Symfony\Component\Validator\Exception\UnexpectedTypeException($constraint, UniqueEntity::class);
        }
        $entity = $value;
        parent::validate($entity, $constraint);

        $unitOfWork = $this->unitOfWork();
        $shape = $this->shape($constraint->entityClass ?? $entity);
        $accessor = PropertyAccess::createPropertyAccessor();

        $criteria = [];
        $hasNullValue = false;

        $fields = array_values((array) $constraint->fields);
        foreach ($fields as $key => $fieldName) {
            //
            // Property path
            $fieldPath = explode(".", $fieldName);
            if (count($fieldPath) > 1) {
                $fieldName = begin($fieldPath);
                if (!$shape->hasAssociation($fieldName)) {
                    throw new ConstraintDefinitionException(sprintf('The field "%s" is expected to be an association.', $fieldName));
                }

                foreach ($accessor->getValue($entity, $fieldName) ?? [] as $association) {
                    $constraint->fields[$key] = implode(".", tail($fieldPath));
                    $classname = explode("\\", get_class($constraint));
                    $classname = array_pop($classname);

                    $defaultMessage = "@validators." . camel2snake($classname);
                    if ($constraint->message == $defaultMessage) {
                        $constraint->message = "@validators." . $this->translator->parseClass($association) . "." . implode(".", tail($fieldPath)) . ".unique";
                        if (!$this->translator->transExists($constraint->message)) {
                            $constraint->message = "@validators.unique_entity";
                        }
                    }

                    $constraint->entity = $association;
                    $constraint->entityClass = get_class($association);

                    $this->validate($association, $constraint);
                }

                return;
            }

            //
            // Default check
            if (!$shape->hasField($fieldName) && !$shape->hasAssociation($fieldName)) {
                throw new ConstraintDefinitionException(sprintf('The field "%s" in "' . get_class($entity) . '" is not mapped, so it cannot be validated for uniqueness.', $fieldName));
            }

            $fieldValue = $accessor->getValue($entity, $fieldName);

            if (null === $fieldValue) {
                $hasNullValue = true;
            }

            if ($constraint->ignoreNull && null === $fieldValue) {
                continue;
            }

            $criteria[$fieldName] = $fieldValue;
        }

        // validation doesn't fail if one of the fields is null and if null values should be ignored
        if ($hasNullValue && $constraint->ignoreNull) {
            return;
        }

        // skip validation if there are no criteria (this can happen when the
        // "ignoreNull" option is enabled and fields to be checked are null
        if (empty($criteria)) {
            return;
        }

        $repository = UnitOfWorks::repositories($unitOfWork)->of($constraint->entityClass ?? $unitOfWork->className($entity));

        // Duplicates among the entities submitted in the same flush (scheduled for insertion, not in the database yet).
        $result = null;
        if ($unitOfWork instanceof Schedule) {
            foreach ($unitOfWork->scheduledInsertions() as $sibling) {
                if ($sibling === $entity || !$sibling instanceof $entity) {
                    continue;
                }
                $same = true;
                foreach ($criteria as $field => $fieldValue) {
                    $same = $same && $accessor->getValue($sibling, $field) == $fieldValue;
                }
                if ($same) {
                    $result = [$sibling];
                    break;
                }
            }
        }

        if (!$result) {
            $result = $repository->{$constraint->repositoryMethod}($criteria);
        }
        if ($result instanceof IteratorAggregate) {
            $result = $result->getIterator();
        }

        /* If the result is a MongoCursor, it must be advanced to the first
         * element. Rewinding should have no ill effect if $result is another
         * iterator implementation.
         */
        if ($result instanceof Iterator) {
            $result->rewind();
            if ($result instanceof Countable && 1 < count($result)) {
                $result = [$result->current(), $result->current()];
            } else {
                $result = $result->valid() && null !== $result->current() ? [$result->current()] : [];
            }
        } elseif (is_array($result)) {
            reset($result);
        } else {
            $result = null === $result ? [] : [$result];
        }

        /* If no entity matched the query criteria or a single entity matched,
         * which is the same as the entity being validated, the criteria is
         * unique.
         */

        if (!$result || (1 === count($result) && self::sameRow($unitOfWork, current($result), $entity))) {
            return;
        }

        $constraint->entity = $entity;

        $errorPath = null !== $constraint->errorPath ? $constraint->errorPath : $fields[0];
        $invalidValue = $criteria[$errorPath] ?? $criteria[$fields[0]];

        // At the field, as symfony/doctrine-bridge's UniqueEntity attaches it (errorPath, else the first field): a form shows
        // the error on that field, not at its top.
        $violation = $this->buildViolation($constraint, $invalidValue)
            ->atPath($errorPath)
            ->setInvalidValue($invalidValue)
            ->setCause($result);
        if (\defined(get_class($constraint).'::NOT_UNIQUE_ERROR')) {
            $violation->setCode(\constant(get_class($constraint).'::NOT_UNIQUE_ERROR'));
        }
        $violation->addViolation();
    }

    /** The entity itself: the same object, or another object of the same row (an identity map cleared since). */
    private static function sameRow(\Omnibridge\Persistence\UnitOfWork $unitOfWork, mixed $found, object $entity): bool
    {
        if ($found === $entity) {
            return true;
        }
        if (!\is_object($found) || $unitOfWork->className($found) !== $unitOfWork->className($entity) || $unitOfWork->isNew($entity)) {
            return false;
        }
        $id = $unitOfWork->identifier($entity);

        return null !== $id && $id == $unitOfWork->identifier($found);
    }
}
