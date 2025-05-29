<?php

namespace Base\Database\Event;

use Doctrine\Common\EventArgs;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;

class DoctrineQueryEventArgs extends EventArgs
{
    protected ?Query $query;
    protected ?QueryBuilder $queryBuilder;
    protected ClassMetadata $classMetadata;
    /**
     * Constructor.
     *
     * @param Query|null $query The Doctrine ORM Query object, or null if not yet created
     * @param string $dql The DQL string associated with the query
     */
    public function __construct(ClassMetadata $classMetadata, ?QueryBuilder $queryBuilder, ?Query $query = null)
    {
        $this->classMetadata = $classMetadata;
        $this->queryBuilder = $queryBuilder;
        $this->query = $query;
    }
    
    /**
     * Get the ClassMetadata associated with the query.
     */
    public function getClassMetadata(): ClassMetadata
    {
        return $this->classMetadata;
    }

    /**
     * Get the current Doctrine Query object (may be null in preQueryCreate).
     */
    public function getQuery(): ?Query
    {
        return $this->query;
    }

    /**
     * Set the current Doctrine Query object.
     *
     * @param Query $query The Doctrine ORM Query object
     */
    public function setQuery(Query $query): void
    {
        $this->query = $query;
    }

    /**
     * Get the current QueryBuilder object (may be null in preQueryCreate).
     */
    public function getQueryBuilder(): ?QueryBuilder
    {
        return $this->queryBuilder;
    }

    /**
     * Set the current QueryBuilder object.
     *
     * @param QueryBuilder $queryBuilder The Doctrine ORM QueryBuilder object
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder): void
    {
        $this->queryBuilder = $queryBuilder;
    }
}