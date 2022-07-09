<?php

namespace Base\Validator;

use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Annotation
 */
abstract class ConstraintEntityValidator extends ConstraintValidator
{
    protected $doctrine;
    protected $em;

    public function __construct(TranslatorInterface $translator, ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        parent::__construct($translator);
    }

    public function getDoctrine()
    {
        return $this->doctrine;
    }

    public function getEntityManager($entityName)
    {
        if (is_object($entityName)) $class = get_class($entityName);
        else $class = $entityName;

        return $this->getDoctrine()->getManagerForClass($class);
    }

    public function getRepository($entityName)
    {
        if (is_object($entityName)) $class = get_class($entityName);
        else $class = $entityName;

        return $this->getEntityManager($class)->getRepository($class);
    }

    public function getOriginalEntity($entity)
    {
        $class = get_class($entity);
        $originalEntity = new $class();

        // Hydrate class
        $attributes = $this->getEntityManager($class)->getUnitOfWork()->getOriginalEntityData($entity);
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

    public function getEntityChangeSet($entity): ?array
    {
        $classMetadata = $this->getClassMetadata($entity);

        $uow = $this->getEntityManager($classMetadata->getName())->getUnitOfWork();
        $uow->recomputeSingleEntityChangeSet($classMetadata->getName(), $entity);

        return $uow->getEntityChangeSet($entity);
    }

    public function getClassMetadata($entityName): ClassMetadata
    {
        if (is_object($entityName)) $class = get_class($entityName);
        else $class = $entityName;

        return $this->getEntityManager($class)->getClassMetadata($class);
    }

    public function buildViolation(Constraint $constraint, $value = null): ConstraintViolationBuilderInterface
    {
        $buildViolation = parent::buildViolation($constraint, $value);

        $entity = $constraint->entity;
        if(!$entity) return $buildViolation;

        $entityExploded = explode("\\", \get_class($entity));
        $entityName = mb_strtolower(array_pop($entityExploded));

        if ($constraint->em) {
            $em = $this->getDoctrine()->getManager($constraint->em);

            if (!$em) {
                throw new ConstraintDefinitionException(sprintf('Object manager "%s" does not exist.', $constraint->em));
            }
        } else {
            $em = $this->getDoctrine()->getManagerForClass(\get_class($entity));

            if (!$em) {
                throw new ConstraintDefinitionException(sprintf('Unable to find the object manager associated with an entity of class "%s".', get_debug_type($entity)));
            }
        }

        $class = $em->getClassMetadata(\get_class($entity));
        $value = $this->formatWithIdentifiers($em, $class, $value);

        $this->setParameter("entity", $this->translator->entity($entityName));

        return $buildViolation->addViolation();
    }

    public function validate($entity, Constraint $constraint)
    {
        $fields = (array) $constraint->fields;
        if (0 === \count($fields)) {
            throw new ConstraintDefinitionException('At least one field has to be specified.');
        }

        if (null === $entity) {
            return false;
        }

        if (!is_instanceof($constraint, $this->constraintClass))
            throw new UnexpectedTypeException($constraint, $this->constraintClass);

        if (!\is_array($constraint->fields) && !\is_string($constraint->fields))
            throw new UnexpectedTypeException($constraint->fields, 'array');

        // if (null !== $constraint->errorPath && !\is_string($constraint->errorPath))
        //     throw new UnexpectedTypeException($constraint->errorPath, 'string or null');

        $this->em = $this->getDoctrine()->getManagerForClass(get_class($entity));
    }

    private function formatWithIdentifiers($em, $class, $value)
    {
        if (!\is_object($value) || $value instanceof \DateTimeInterface) {
            return $this->formatValue($value, self::PRETTY_DATE);
        }

        if (method_exists($value, '__toString')) {
            return (string) $value;
        }

        if ($class->getName() !== $idClass = \get_class($value)) {
            // non unique value might be a composite PK that consists of other entity objects
            if ($em->getMetadataFactory()->hasMetadataFor($idClass)) {
                $identifiers = $em->getClassMetadata($idClass)->getIdentifierValues($value);
            } else {
                // this case might happen if the non unique column has a custom doctrine type and its value is an object
                // in which case we cannot get any identifiers for it
                $identifiers = [];
            }
        } else {
            $identifiers = $class->getIdentifierValues($value);
        }

        if (!$identifiers) {
            return sprintf('object("%s")', $idClass);
        }

        array_walk($identifiers, function (&$id, $field) {
            if (!\is_object($id) || $id instanceof \DateTimeInterface) {
                $idAsString = $this->formatValue($id, self::PRETTY_DATE);
            } else {
                $idAsString = sprintf('object("%s")', \get_class($id));
            }

            $id = sprintf('%s => %s', $field, $idAsString);
        });

        return sprintf('object("%s") identified by (%s)', $idClass, implode(', ', $identifiers));
    }
}
