<?php

namespace Base\Repository\Thread;

use App\Entity\Thread\Like;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Like|null find($id, $lockMode = null, $lockVersion = null)
 * @method Like|null findOneBy(array $criteria, array $orderBy = null)
 * @method Like[]    findAll()
 * @method Like[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LikeRepository extends ServiceEntityRepository implements Likes
{
    /** Likes (step R28): ThreadController's finder. */
    public function oneByThreadAndUser(\Base\Entity\Thread $thread, ?\Base\Entity\User $user): ?\Base\Entity\Thread\Like
    {
        return $this->findOneByThreadAndUser($thread, $user);
    }

}
