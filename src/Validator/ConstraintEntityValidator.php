<?php

namespace Base\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use function count;
use function get_class;
use function is_array;
use function is_string;

/**
 * @Annotation
 */
abstract class ConstraintEntityValidator extends ConstraintValidator
{
    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $em;

    /**
     * @param $entity
     * @return mixed
     */
    public function getOriginalEntity($entity)
    {
        $class = get_class($entity);
        $originalEntity = new $class();

        // Hydrate class
        $attributes = $this->em->getUnitOfWork()->getOriginalEntityData($entity);
        foreach ($attributes as $key => $value) {
            // One gets the setter's name matching the attribute.
            $method = 'set' . mb_ucfirst($key);

            // If the matching setter exists
            if (method_exists($originalEntity, $method)) {
                // One calls the setter.
                $originalEntity->$method($value);
            }
        }

        return $originalEntity;
    }

    /**
     * @param $entity
     * @return array|null
     */
    public function getEntityChangeSet($entity): ?array
    {
        $classMetadata = $this->getClassMetadata($entity);

        $uow = $this->em->getUnitOfWork();
        $uow->recomputeSingleEntityChangeSet($classMetadata, $entity);

        return $uow->getEntityChangeSet($entity);
    }

    /**
     * @param $entityName
     * @return ClassMetadata
     */
    public function getClassMetadata($entityName): ClassMetadata
    {
        if (is_object($entityName)) {
            $class = get_class($entityName);
        } else {
            $class = $entityName;
        }

        return $this->em->getClassMetadata($class);
    }

    /**
     * @param Constraint $constraint
     * @param $value
     * @return ConstraintViolationBuilderInterface
     */
    public function buildViolation(Constraint $constraint, $value = null): ConstraintViolationBuilderInterface
    {
        $buildViolation = parent::buildViolation($constraint, $value);

        $entity = $constraint->entity;
        if (!$entity) {
            return $buildViolation;
        }

        if ($constraint->em) {
            $em = $this->getDoctrine()->getManager($constraint->em);

            if (!$em) {
                throw new ConstraintDefinitionException(sprintf('Object manager "%s" does not exist.', $constraint->em));
            }
        } else {
            $em = $this->getDoctrine()->getManagerForClass($constraint->entityClass ?? get_class($entity));
            if (!$em) {
                throw new ConstraintDefinitionException(sprintf('Unable to find the object manager associated with an entity of class "%s".', get_debug_type($entity)));
            }
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

        // if (null !== $constraint->errorPath && !\is_string($constraint->errorPath))
        //     throw new UnexpectedTypeException($constraint->errorPath, 'string or null');

        $this->em = $this->getDoctrine()->getManagerForClass($constraint->entityClass ?? get_class($entity));
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
