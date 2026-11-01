<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Backend\ConditionalUpdates;

/** ConditionalUpdates on Doctrine: one DQL UPDATE (plan "omnibase agnostic", step F2d; moves to omnibridge/doctrine at F3). */
final class DoctrineConditionalUpdates implements ConditionalUpdates
{
    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function compareAndSet(object $entity, string $field, mixed $from, mixed $to): bool
    {
        if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
            throw new \InvalidArgumentException(sprintf('"%s" is no field name.', $field));
        }

        $entityManager = $this->unitOfWork->entityManager();
        $metadata = $entityManager->getClassMetadata($entity::class);
        $identifier = $metadata->getSingleIdentifierFieldName();

        return 1 === $entityManager->createQuery('UPDATE '.$metadata->getName().' e SET e.'.$field.' = :to WHERE e.'.$identifier.' = :id AND e.'.$field.' = :from')
            ->setParameter('to', $to)
            ->setParameter('id', $this->unitOfWork->identifier($entity))
            ->setParameter('from', $from)
            ->execute();
    }
}
