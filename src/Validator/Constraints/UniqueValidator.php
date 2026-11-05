<?php

namespace Base\Validator\Constraints;

use Base\Contract\Runtime;
use Base\Database\Repository\Repositories;
use Base\Database\UnitOfWorks;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Unique's check (plan "omnibase agnostic", step E1): UniqueEntityValidator's steps, the record
 * looked up through the persistence's Repositories instead of Doctrine's registry. The fields are
 * read from the record's properties (Doctrine's reflection fields read the same values), the lookup
 * is `repositoryMethod($criteria)` - findBy asked for two at most -, and the violation is the one
 * UniqueEntity built: at errorPath (the first field by default), its value formatted the same way,
 * the code NOT_UNIQUE_ERROR, the records found as its cause.
 */
class UniqueValidator extends ConstraintValidator
{
    public function __construct(private readonly ?UnitOfWork $unitOfWork = null, private readonly ?Repositories $repositories = null)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Unique) {
            throw new UnexpectedTypeException($constraint, Unique::class);
        }
        if (!\is_array($constraint->fields) && !\is_string($constraint->fields)) {
            throw new UnexpectedTypeException($constraint->fields, 'array');
        }
        if (null !== $constraint->errorPath && !\is_string($constraint->errorPath)) {
            throw new UnexpectedTypeException($constraint->errorPath, 'string or null');
        }

        $fields = (array) $constraint->fields;
        if (!$fields) {
            throw new ConstraintDefinitionException('At least one field has to be specified.');
        }
        if (null === $value) {
            return;
        }
        if (!\is_object($value)) {
            throw new UnexpectedValueException($value, 'object');
        }

        $unitOfWork = $this->unitOfWork ?? Runtime::unitOfWork();
        $criteria = [];
        $hasIgnorableNullValue = false;
        foreach ($fields as $objectFieldName => $fieldName) {
            $property = \is_int($objectFieldName) ? $fieldName : $objectFieldName;
            $fieldValue = $this->read($value, $property);
            if (null === $fieldValue && $this->ignoreNullForField($constraint, $fieldName)) {
                $hasIgnorableNullValue = true;

                continue;
            }
            $criteria[$fieldName] = $fieldValue;
        }
        if ($hasIgnorableNullValue || !$criteria) {
            return;
        }

        $repositories = $this->repositories ?? UnitOfWorks::repositories($unitOfWork);
        $repository = $repositories->of($constraint->entityClass ?? $unitOfWork->className($value));
        $arguments = 'findBy' === $constraint->repositoryMethod ? [$criteria, null, 2] : [$criteria];
        $result = $repository->{$constraint->repositoryMethod}(...$arguments);

        if ($result instanceof \IteratorAggregate) {
            $result = $result->getIterator();
        }
        if ($result instanceof \Iterator) {
            $result->rewind();
            if ($result instanceof \Countable && 1 < \count($result)) {
                $result = [$result->current(), $result->current()];
            } else {
                $result = $result->valid() && null !== $result->current() ? [$result->current()] : [];
            }
        } elseif (\is_array($result)) {
            reset($result);
        } else {
            $result = null === $result ? [] : [$result];
        }

        if (!$result || (1 === \count($result) && current($result) === $value)) {
            return;
        }

        $errorPath = $constraint->errorPath ?? current($fields);
        $invalidValue = $criteria[$errorPath] ?? $criteria[current($fields)];

        $this->context->buildViolation($constraint->message)
            ->atPath($errorPath)
            ->setParameter('{{ value }}', $this->formatWithIdentifiers($unitOfWork, $invalidValue))
            ->setInvalidValue($invalidValue)
            ->setCode(Unique::NOT_UNIQUE_ERROR)
            ->setCause($result)
            ->addViolation();
    }

    /** The property's value, read as Doctrine's reflection field reads it (whatever its visibility). */
    private function read(object $record, string $property): mixed
    {
        $class = new \ReflectionClass($record);
        while (!$class->hasProperty($property)) {
            $class = $class->getParentClass();
            if (false === $class) {
                throw new ConstraintDefinitionException(\sprintf('The field "%s" is not mapped by Doctrine, so it cannot be validated for uniqueness.', $property));
            }
        }
        $reflection = $class->getProperty($property);

        return $reflection->isInitialized($record) ? $reflection->getValue($record) : null;
    }

    private function ignoreNullForField(Unique $constraint, string $fieldName): bool
    {
        if (\is_bool($constraint->ignoreNull)) {
            return $constraint->ignoreNull;
        }

        return \in_array($fieldName, (array) $constraint->ignoreNull, true);
    }

    private function formatWithIdentifiers(UnitOfWork $unitOfWork, mixed $value): string
    {
        if (!\is_object($value) || $value instanceof \DateTimeInterface) {
            return $this->formatValue($value, self::PRETTY_DATE);
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        $idClass = $unitOfWork->className($value);
        try {
            $identifier = $unitOfWork->identifier($value);
        } catch (\Throwable) {
            $identifier = null;
        }
        $identifiers = null === $identifier ? [] : (\is_array($identifier) ? $identifier : ['id' => $identifier]);
        if (!$identifiers) {
            return \sprintf('object("%s")', $idClass);
        }
        array_walk($identifiers, function (&$id, $field) {
            $id = \sprintf('%s => %s', $field, !\is_object($id) || $id instanceof \DateTimeInterface ? $this->formatValue($id, self::PRETTY_DATE) : \sprintf('object("%s")', $id::class));
        });

        return \sprintf('object("%s") identified by (%s)', $idClass, implode(', ', $identifiers));
    }
}
