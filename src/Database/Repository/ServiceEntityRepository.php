<?php

namespace Base\Database\Repository;

use Base\Database\Mapping\ClassMetadataCompletor;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Entity\EntityHydrator;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

/**
 * @method Entity[]    findBy*(...array $customs,
 *      array $criteria, array ??array $orderBy = null, $limit = null, $offset = null)
 */
class ServiceEntityRepository extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository
{
    /**
     * @var ClassMetadata
     */
    protected ClassMetadata $classMetadata;

    /**
     * @var ClassMetadataCompletor
     */
    protected ClassMetadataCompletor $classMetadataCompletor;

    /**
     * @var ServiceEntityParser
     */
    protected ServiceEntityParser $serviceParser;

    /**
     * @return array|string|string[]|null
     */
    public static function getFqcnEntityName()
    {
        return preg_replace(
            ['/\\\\Repository\\\\/', '/Repository$/'],
            ["\\\\Entity\\\\", ""],
            static::class
        );
    }

    public function __construct(ManagerRegistry $doctrine, ?string $entityName = null)
    {
        parent::__construct($doctrine, $entityName ?? $this->getFqcnEntityName());

        $entityManager = $this->getEntityManager();
        $classMetadataManipulator = new ClassMetadataManipulator($doctrine, $entityManager);
        
        $this->classMetadata = $entityManager->getClassMetadata($entityName ?? $this->getFqcnEntityName());
        $this->classMetadataCompletor = $classMetadataManipulator->getClassMetadataCompletor($entityName ?? $this->getFqcnEntityName());

        $entityHydrator = new EntityHydrator($entityManager, $classMetadataManipulator);
        $this->serviceParser = new ServiceEntityParser($this, $entityManager, $classMetadataManipulator, $entityHydrator);
    }

    /**
     * @return ClassMetadata|null
     */
    public function getClassMetadata(): ClassMetadata
    {
        return $this->classMetadata;
    }

    /**
     * @return ClassMetadataCompletor|null
     */
    public function getClassMetadataCompletor(): ?ClassMetadataCompletor
    {
        return $this->classMetadataCompletor;
    }

    /**
     * @param $method
     * @param $arguments
     * @return mixed
     */
    public function __call($method, $arguments): mixed
    {
        return $this->serviceParser->parse($method, $arguments);
    }

    /**
     * @param $id
     * @param $lockMode
     * @param $lockVersion
     * @return object|null
     */
    public function find($id, $lockMode = null, $lockVersion = null): ?object
    {
        return $this->findOneById($id, $lockMode, $lockVersion);
    }

    public function findAll(): array
    {
        return $this->__call(__METHOD__, [])->getResult();
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param $limit
     * @param $offset
     * @return array|object[]
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->__call(__METHOD__, [$criteria, $orderBy, $limit, $offset])->getResult();
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        return $this->__call(__METHOD__, [$criteria, $orderBy]);
    }

    public function count(array $criteria = []): int
    {
        return $this->__call(__METHOD__, [$criteria]);
    }

    public function flush(bool $autoclear = true)
    {
        $this->getEntityManager()->flush();
        if($autoclear) $this->clear();
    }

    public function clear()
    {
        $this->getEntityManager()->clear();
    }

    /**
     * @param $entity
     * @return void
     * @throws Exception
     */
    public function persist($entity, bool $flush = false): void
    {
        dump($this->getFqcnEntityName());
        $entityClass = \property_exists($this, '_entityName') ? $this->_entityName : $this->getFqcnEntityName(); // Doctrine ORM 2 vs. 3
        if (!is_object($entity) || (!$entity instanceof $entityClass && !is_subclass_of($entity, $entityClass))) {
            $class = (is_object($entity) ? get_class($entity) : "null");
            throw new Exception("Repository \"" . static::class . "\" is expected \"" . $entityClass . "\" entity, you passed \"" . $class . "\"");
        }

        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove($entity, bool $flush = false): void
    {
        dump($this->getFqcnEntityName());
        $entityClass = \property_exists($this, '_entityName') ? $this->_entityName : $this->getFqcnEntityName(); // Doctrine ORM 2 vs. 3
        if (!is_object($entity) || (!$entity instanceof $entityClass && !is_subclass_of($entity, $entityClass))) {
            $class = (is_object($entity) ? get_class($entity) : "null");
            throw new Exception("Repository \"" . static::class . "\" is expected \"" . $entityClass . "\" entity, you passed \"" . $class . "\"");
        }

        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
