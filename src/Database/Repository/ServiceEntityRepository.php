<?php

namespace Base\Database\Repository;

use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Entity\EntityHydrator;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method Entity[]    findBy*(...array $customs,
 *      array $criteria, array ??array $orderBy = null, $limit = null, $offset = null)
 */

class ServiceEntityRepository extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository
{
    /**
     * @var ClassMetadataCompletor
     */
    protected $classMetadataCompletor;

    /**
     * @var ServiceEntityParser
     */
    protected $serviceParser;

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
        $this->classMetadataCompletor = $classMetadataManipulator->getClassMetadataCompletor( $entityName ?? $this->getFqcnEntityName());
        
        $entityHydrator = new EntityHydrator($entityManager, $classMetadataManipulator);
        $this->serviceParser = new ServiceEntityParser($this, $entityManager, $classMetadataManipulator, $entityHydrator);
    }

    public function getClassMetadataCompletor() { return $this->classMetadataCompletor; }

    public function __call   ($method, $arguments) : mixed { return $this->serviceParser->parse($method, $arguments); }

    public function find     ($id, $lockMode = null, $lockVersion = null                            ):?object { return $this->findOneById($id, $lockMode, $lockVersion); }
    public function findAll  (                                                                      ):array   { dump($this->__call(__METHOD__, [])); return $this->__call(__METHOD__, [])->getResult(); }
    public function findBy   (array $criteria, ?array $orderBy = null, $limit = null, $offset = null):array   { return $this->__call(__METHOD__, [$criteria, $orderBy, $limit, $offset])->getResult(); }
    public function findOneBy(array $criteria, ?array $orderBy = null                               ):?object { return $this->__call(__METHOD__, [$criteria, $orderBy]); }
    public function count    (array $criteria                                                       ):int     { return $this->__call(__METHOD__, [$criteria]); }

    public function flush($entity = null)
    {
        $entityFqcn = self::getFqcnEntityName();
        $entityList = array_filter(!is_array($entity) ? [$entity] : $entity, fn($e) => $e instanceof $entityFqcn);

        if(count($entityList) || $entity === null)
            $this->getEntityManager()->flush($entity);
    }

    public function persist($entity) {

        if(!is_object($entity) || (!$entity instanceof $this->_entityName && !is_subclass_of($entity, $this->_entityName))) {
            $class = (is_object($entity) ? get_class($entity) : "null");
            throw new \Exception("Repository \"".static::class."\" is expected \"".$this->_entityName."\" entity, you passed \"".$class."\"");
        }

        $this->getEntityManager()->persist($entity);
    }
}
