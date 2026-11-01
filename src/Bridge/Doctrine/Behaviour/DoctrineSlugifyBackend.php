<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\SlugifyBackend;

/** #[Slugify]'s part on Doctrine (plan "omnibase agnostic", step F2b; moves to omnibridge/doctrine at F3). */
final class DoctrineSlugifyBackend implements SlugifyBackend
{
    /** @var array<string, bool> table.column => unique alone, asked once per process */
    private static array $uniqueAlone = [];

    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function pendingWrites(): array
    {
        $unitOfWork = $this->unitOfWork->entityManager()->getUnitOfWork();

        $entities = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $entities[] = $entity;
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $entities[] = $entity;
        }

        return $entities;
    }

    public function uniqueAlone(string $class, string $property): bool
    {
        try {
            $entityManager = $this->unitOfWork->entityManager();
            $classMetadata = $entityManager->getClassMetadata($class);
            $table = $classMetadata->getTableName();
            $column = $classMetadata->getColumnName($property);
            $key = $table.'.'.$column;
            if (array_key_exists($key, self::$uniqueAlone)) {
                return self::$uniqueAlone[$key];
            }

            self::$uniqueAlone[$key] = false;
            foreach ($entityManager->getConnection()->createSchemaManager()->listTableIndexes($table) as $index) {
                if ($index->isUnique() && !$index->isPrimary() && array_map('strtolower', $index->getUnquotedColumns()) === [strtolower($column)]) {
                    self::$uniqueAlone[$key] = true;
                }
            }

            return self::$uniqueAlone[$key];
        } catch (\Throwable) {
            return false;
        }
    }
}
