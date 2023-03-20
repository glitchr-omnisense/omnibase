<?php

namespace Base\Repository;

use Base\Entity\Thread;
use Base\Annotations\Traits\HierarchifyTrait;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Enum\WorkflowState;

/**
 * @method Thread|null find($id, $lockMode = null, $lockVersion = null)
 * @method Thread|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Thread[]    findAll()
 * @method Thread[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ThreadRepository extends ServiceEntityRepository
{
    use HierarchifyTrait;

    public function countForChildrenIn($thread)
    {
        $nDiscussions = $this->countByParentAndWorkflow($thread, WorkflowState::APPROVED, [], "", null, ["children"]);

        $nComments = [];
        foreach ($nDiscussions as $entry) {
            $nComments[$entry[$thread->getId()]] = ($nComments[$entry[$thread->getId()]] ?? 0) + $entry["count"];
        }

        return $nComments;
    }
}
