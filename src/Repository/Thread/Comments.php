<?php

namespace Base\Repository\Thread;

use Base\Database\Repository\Entities;
use Base\Entity\Thread\Comment;

/**
 * The comments of the threads (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): CommentRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<Comment>
 */
interface Comments extends Entities
{
    /** The newest comment written from an address (FormGuard's flood check). */
    public function findLastFromIp(string $ip): ?Comment;
}
