<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Backend\BulkDeletes;

/** BulkDeletes on Doctrine: one DQL DELETE (plan "omnibase agnostic", step F2e; moves to omnibridge/doctrine at F3). */
final class DoctrineBulkDeletes implements BulkDeletes
{
    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function deleteWhere(string $class, array $criteria): int
    {
        $where = [];
        $parameters = [];
        foreach (array_keys($criteria) as $i => $field) {
            if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
                throw new \InvalidArgumentException(sprintf('"%s" is no field name.', $field));
            }
            $where[] = 'e.'.$field.' = :p'.$i;
            $parameters['p'.$i] = $criteria[$field];
        }

        $query = $this->unitOfWork->entityManager()->createQuery('DELETE FROM '.$class.' e'.([] === $where ? '' : ' WHERE '.implode(' AND ', $where)));
        foreach ($parameters as $name => $value) {
            $query->setParameter($name, $value);
        }

        return (int) $query->execute();
    }
}
