<?php

namespace Base\Validator;

use Base\Contract\Runtime;
use Base\Database\Mapping\EntityShape;
use Base\Database\UnitOfWorks;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use function count;
use function get_class;
use function is_array;
use function is_string;

/**
 * The validators of an entity's state (UniqueEntity, StringCaseEntity...), on any persistence: the host's unit of work
 * (its changes, what it holds) and the entities' shapes - nothing of an ORM.
 */
abstract class ConstraintEntityValidator extends ConstraintValidator
{
    /** The persistence chosen's unit of work, else the runtime's. */
    protected ?UnitOfWork $unitOfWork = null;

    public function setUnitOfWork(?UnitOfWork $unitOfWork): void
    {
        $this->unitOfWork = $unitOfWork;
    }

    protected function unitOfWork(): UnitOfWork
    {
        $unitOfWork = $this->unitOfWork ?? Runtime::find(UnitOfWork::class);

        return $unitOfWork instanceof UnitOfWork ? $unitOfWork : throw new ConstraintDefinitionException(sprintf('%s validates an entity through the host\'s unit of work: none is set.', static::class));
    }

    /** The shape (fields, associations) of an entity or a class. */
    protected function shape(object|string $entityOrClass): EntityShape
    {
        return UnitOfWorks::shapes($this->unitOfWork())->shape(is_object($entityOrClass) ? $this->unitOfWork()->className($entityOrClass) : $entityOrClass);
    }

    /**
     * The entity as it was read (its changed fields set back to their former values, on a copy); null for an entity
     * the persistence holds no row of yet.
     */
    public function getOriginalEntity($entity)
    {
        $unitOfWork = $this->unitOfWork();
        if (!$unitOfWork->contains($entity) || $unitOfWork->isNew($entity)) {
            return null;
        }
        $original = clone $entity;
        $accessor = PropertyAccess::createPropertyAccessor();
        foreach ($this->getEntityChangeSet($entity) as $field => [$before]) {
            if ($accessor->isWritable($original, $field)) {
                $accessor->setValue($original, $field, $before);
            }
        }

        return $original;
    }

    /** What changed in the entity since it was read: field => [before, after]. */
    public function getEntityChangeSet($entity): ?array
    {
        $unitOfWork = $this->unitOfWork();
        $unitOfWork->recompute($entity);

        return $unitOfWork->changes($entity);
    }

    public function buildViolation(Constraint $constraint, $value = null): ConstraintViolationBuilderInterface
    {
        $buildViolation = parent::buildViolation($constraint, $value);

        $entity = $constraint->entity;
        if (!$entity) {
            return $buildViolation;
        }

        $this->setParameter("entity", $this->translator->transEntity($entity));
        return $buildViolation;
    }

    /**
     * @param $value
     * @param Constraint $constraint
     * @return void
     * @throws \Exception
     */
    public function validate($value, Constraint $constraint): void
    {
        $entity = $value;
        $fields = (array)$constraint->fields;
        if (0 === count($fields)) {
            throw new ConstraintDefinitionException('At least one field has to be specified.');
        }

        if (null === $entity) {
            return;
        }

        if (!is_instanceof($constraint, $this->constraintClass)) {
            throw new UnexpectedTypeException($constraint, $this->constraintClass);
        }

        if (!is_array($constraint->fields) && !is_string($constraint->fields)) {
            throw new UnexpectedTypeException($constraint->fields, 'array');
        }
    }

    /**
     * @param Constraint $constraint
     * @return mixed|string
     */
    protected function formatIdentifier(Constraint $constraint)
    {
        $class = get_class($constraint->entity);

        $message = $constraint->message;
        while ($class !== false) {
            $className = explode("\\", $class);
            array_shift($className);
            array_shift($className);

            $id = trim("@entities." . implode(".", array_map(fn($c) => camel2snake($c), $className)) . "._validators." . $constraint->message, ".");
            if ($this->translator->transExists($id)) {
                $message = $id;
                break; // Intl found
            }

            $class = get_parent_class($class);
        }

        return $message;
    }
}
