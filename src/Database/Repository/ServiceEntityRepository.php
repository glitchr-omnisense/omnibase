<?php

namespace Base\Database\Repository;

use Base\Entity\Thread;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Thread|null find($id, $lockMode = null, $lockVersion = null)
 * @method Thread|null findOneBy(array $criteria, array ?array $orderBy = null, $groupBy = null)
 * @method Thread|null findLastBy(array $criteria, array ?array $orderBy = null, $groupBy = null)
 * @method Thread[]    findAll(?array $orderBy = null, $groupBy = null)
 * @method Thread[]    findBy(array $criteria, array ?array $orderBy = null, $groupBy = null, $limit = null, $offset = null)
 */
class ServiceEntityRepository extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository
{
    public static function getFqcnEntityName()
    {
        return preg_replace(
            ['/\\\\Repository\\\\/', '/Repository$/'],
            ["\\\\Entity\\\\", ""], 
            static::class
        );
    }

    protected $serviceParser;
    public function __construct(ManagerRegistry $registry, ?string $entityName = null)
    {
        parent::__construct($registry, $entityName ?? $this->getFqcnEntityName());
        $this->serviceParser = new ServiceEntityParser($this, $this->getClassMetadata());
    }

    public function __call($method, $arguments) : mixed
    {
        return $this->serviceParser->parse($method, $arguments);
    }

    public function flush() { return $this->getEntityManager()->flush(); }
    public function persist($entity) {

        $entityName = self::getFqcnEntityName();
        if(!is_object($entity) || (!$entity instanceof $entityName && !is_subclass_of($entity, $entityName))) {
            $class = (is_object($entity) ? get_class($entity) : "null");
            throw new \Exception("Repository \"".static::class."\" is expected \"".$entityName."\" entity, you passed \"".$class."\"");
        }

        $this->getEntityManager()->persist($entity);
    }
}