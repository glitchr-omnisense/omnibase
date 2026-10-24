<?php

namespace Base\Repository\Thread;

use Base\Database\Repository\Entities;
use Base\Entity\Thread\Mention;
use Base\Entity\Thread;

/**
 * The mentions of members in the threads (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): MentionRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<Mention>
 */
interface Mentions extends Entities
{
    /**
     * A thread's mentions, through the finder's cache.
     *
     * @return list<Mention>
     */
    public function byThread(Thread $thread): array;
}
