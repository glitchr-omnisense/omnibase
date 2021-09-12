<?php

namespace Base\Repository;

use Base\Database\Annotation\EntityHierarchy;
use Base\Entity\Thread;
use Doctrine\Persistence\Event\ManagerEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use Base\Repository\Traits\EntityHierarchyTrait;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Thread|null find($id, $lockMode = null, $lockVersion = null)
 * @method Thread|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Thread[]    findAll()
 * @method Thread[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ThreadRepository extends ServiceEntityRepository
{
    use EntityHierarchyTrait;
    
    public function __construct(ManagerRegistry $registry, ?string $entityClass = null)
    {
        parent::__construct($registry, $entityClass ?? Thread::class);
    }

    public function countForChildrenIn($thread)
    {
        $nDiscussions = $this->countByParent($thread, [], "", null, ["children"]);
        
        $nComments = [];
        foreach($nDiscussions as $entry)
            $nComments[$entry["children"]] = ($nComments[$entry["children"]] ?? 0) + $entry["count"];

        return $nComments;
    }
}
