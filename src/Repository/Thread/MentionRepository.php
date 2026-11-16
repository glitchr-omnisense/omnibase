<?php

namespace Base\Repository\Thread;

use Base\Entity\Thread\Mention;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Repository\Thread\Mentions;

/**
 * @extends ServiceEntityRepository<Mention>
 *
 * @method Mention|null find($id)
 * @method Mention|null findOneBy(array $criteria, array $orderBy = null)
 * @method Mention[]    findAll()
 * @method Mention[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MentionRepository extends ServiceEntityRepository implements Mentions
{
    /** Mentions (step R28): ThreadSubscriber's finder. */
    public function byThread(\Base\Entity\Thread $thread): array
    {
        return $this->cacheByThread($thread)->getResult();
    }

}
