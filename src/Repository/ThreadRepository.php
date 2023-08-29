<?php

namespace Base\Repository;

use Base\Entity\Thread;
use Base\Annotations\Traits\HierarchifyTrait;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Enum\WorkflowState;

/**
 * @method Thread|null find($id, $lockMode = null, $lockVersion = null)
 * @method Thread|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Thread[]    findAll()
 * @method Thread[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class ThreadRepository extends ServiceEntityRepository
{
    use HierarchifyTrait;

    /**
     * @param $thread
     * @return array
     */
    public function countForChildrenIn($thread)
    {
        $nDiscussions = $this->countByParentAndWorkflow($thread, WorkflowState::APPROVED, [], "", null, ["children"]);

        $nComments = [];
        foreach ($nDiscussions as $entry) {
            $nComments[$entry[$thread->getId()]] = ($nComments[$entry[$thread->getId()]] ?? 0) + $entry["count"];
        }

        return $nComments;
    }

    public function cacheByInsensitiveIdentifier($identifier, array $fields = []) { return $this->findByInsensitiveIdentifier($identifier, $fields, true); }
    public function findByInsensitiveIdentifier($identifier, array $fields = [], $cacheable = false)
    {
        if(empty($fields)) $fields[] = "slug";

        $identifier = preg_replace("/%$/", "", $identifier);
        $qb = $this->createQueryBuilder('u')
            ->setCacheable($cacheable)
            ->setCacheRegion($this->getClassMetadata()->cache["region"] ?? null)
            ->setParameter('identifier', "%".strtolower($identifier)."%");

        foreach($fields as $field) {
            $qb->orWhere('LOWER(u.'.$field.') LIKE :identifier');
        }

        return $qb->getQuery();
    }
}
