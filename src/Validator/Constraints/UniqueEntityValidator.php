<?php

namespace Base\Validator\Constraints;

use Base\Contract\Runtime;
use Base\Database\UnitOfWorks;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Doctrine\Omnibase\DoctrineLocator;
use Base\Validator\ConstraintEntity;
use Countable;
use Iterator;
use IteratorAggregate;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Base\Validator\ConstraintEntityValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use function count;
use function get_class;
use function is_array;

class UniqueEntityValidator extends ConstraintEntityValidator
{
    /** The persistence chosen's unit of work (omnisense/omnibridge's), else the runtime's; none: Doctrine's alone. */
    protected ?UnitOfWork $unitOfWork = null;

    public function setUnitOfWork(?UnitOfWork $unitOfWork): void
    {
        $this->unitOfWork = $unitOfWork;
    }

    /**
     * @param $value
     * @param Constraint $constraint
     * @throws \Exception
     */
    public function validate($value, Constraint $constraint): void
    {
        $entity = $value;
        parent::validate($entity, $constraint);

        $em = $constraint->em ?? DoctrineLocator::objectManager($constraint->entityClass ?? get_class($entity));
        if (!$em) {
            throw new ConstraintDefinitionException(sprintf('Unable to find the object manager associated with an entity of class "%s".', get_debug_type($entity)));
        }

        $classMetadata = $this->getClassMetadata($constraint->entityClass ?? $entity);

        // An entity another persistence read (omnisense/omnibridge's unit of work, Eloquent's: plan "omnibase agnostic",
        // step F7d-3) is looked up there: Doctrine's repository gives another instance of its row, which made every
        // account's own name "already used". Doctrine's metadata still names the fields.
        $unitOfWork = $this->unitOfWork ?? Runtime::find(UnitOfWork::class);
        $other = null !== $unitOfWork && !$em->contains($entity) && $unitOfWork->contains($entity) ? $unitOfWork : null;

        $criteria = [];
        $hasNullValue = false;

        $fields = array_map(fn($f) => $classMetadata->getFieldName($f), $constraint->fields);
        foreach ($fields as $key => $fieldName) {
            //
            // Property path
            $fieldPath = explode(".", $fieldName);
            if (count($fieldPath) > 1) {
                $fieldName = begin($fieldPath);
                if (!$classMetadata->hasAssociation($fieldName)) {
                    throw new ConstraintDefinitionException(sprintf('The field "%s" is expected to be an association.', $fieldName));
                }

                foreach ($classMetadata->getFieldValue($entity, $fieldName) as $association) {
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
            if (!$classMetadata->hasField($fieldName) && !$classMetadata->hasAssociation($fieldName)) {
                throw new ConstraintDefinitionException(sprintf('The field "%s" in "' . get_class($entity) . '" is not mapped by Doctrine, so it cannot be validated for uniqueness.', $fieldName));
            }

            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $fieldValue = $propertyAccessor->getValue($entity, $fieldName);

            if (null === $fieldValue) {
                $hasNullValue = true;
            }

            if ($constraint->ignoreNull && null === $fieldValue) {
                continue;
            }

            $criteria[$fieldName] = $fieldValue;
            if (null === $other && null !== $criteria[$fieldName] && $classMetadata->hasAssociation($fieldName)) {
                /* Ensure the Proxy is initialized before using reflection to
                 * read its identifiers. This is necessary because the wrapped
                 * getter methods in the Proxy are being bypassed.
                 */
                $em->initializeObject($criteria[$fieldName]);
            }
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

        if (null !== $constraint->entityClass) {
            /* Retrieve repository from given entity name.
             * We ensure the retrieved repository can handle the entity
             * by checking the entity is the same, or subclass of the supported entity.
             */
            $repository = null !== $other ? UnitOfWorks::repositories($other)->of($constraint->entityClass) : $em->getRepository($constraint->entityClass);

            if (!$repository) {
                throw new ConstraintDefinitionException(sprintf('No corresponding "%s" entity repository found.', $constraint->entityClass));
            }
        } else {
            $repository = null !== $other ? UnitOfWorks::repositories($other)->of(get_class($entity)) : $em->getRepository(get_class($entity));
        }

        // Find duplicates among the submitted entities (Doctrine's identity map; none on another persistence)
        $identityMap = null !== $other ? [] : ($this->em->getUnitOfWork()->getIdentityMap()[get_root_class($entity)] ?? []);
        $siblingEntities = array_filter(
            $identityMap ?? null,
            fn($k) => ($k != $entity->getId()) && $identityMap[$k] instanceof $entity,
            ARRAY_FILTER_USE_KEY
        );

        $result = null;
        foreach ($siblingEntities as $siblingEntity) {
            if ($entity->getId() > $siblingEntity->getId()) {
                continue;
            }
            if ($classMetadata->getFieldValue($siblingEntity, $fieldName) == $classMetadata->getFieldValue($entity, $fieldName)) {
                $result = $siblingEntity->getId();
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

        if (!$result || (1 === count($result) && current($result) === $entity)) {
            return;
        }

        if ($constraint instanceof ConstraintEntity) {
            $constraint->entity = $entity;
        }

        $errorPath = null !== $constraint->errorPath ? $constraint->errorPath : $fields[0];
        $invalidValue = $criteria[$errorPath] ?? $criteria[$fields[0]];

        $this->buildViolation($constraint, $invalidValue)->addViolation();
    }
}
