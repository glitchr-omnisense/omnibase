<?php

namespace Base\Repository\Thread;

use Base\Database\Repository\Entities;
use Base\Entity\Thread\Like;
use Base\Entity\Thread;
use Base\Entity\User;

/**
 * The likes of the threads (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): LikeRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<Like>
 */
interface Likes extends Entities
{
    /** A member's like of a thread, read from the database (no result cache: the like's own page writes it). */
    public function oneByThreadAndUser(Thread $thread, ?User $user): ?Like;
}
