<?php

namespace Base\Repository\Thread;

use Base\Database\Repository\Entities;
use Base\Entity\Thread;
use Base\Entity\Thread\Comment;

/**
 * The comments of the threads (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): CommentRepository answers it with the queries
 * its callers ran by name before. Since the plan "omnibase agnostic"'s step E2', what
 * omnibase/blog and omnibase/video ask of it too (the comments online and waiting).
 *
 * @extends Entities<Comment>
 */
interface Comments extends Entities
{
    /** The newest comment written from an address (FormGuard's flood check). */
    public function findLastFromIp(string $ip): ?Comment;

    /**
     * The comments online under a thread - or the visitors' book when $thread is
     * null -, the top-level ones, newest first; their replies hang under each
     * (Comment::getVisibleReplies()). A thread in the trash keeps its comments:
     * the thread is compared by its key, not through a join the trash scopes.
     *
     * @return list<Comment>
     */
    public function findVisible(?Thread $thread, int $limit = 200): array;

    /** How many comments are online under a thread, replies included (the visitors' book for null). */
    public function countVisible(?Thread $thread): int;

    /** How many comments wait for moderation. */
    public function countPending(): int;

    /**
     * The comments waiting for moderation, oldest first.
     *
     * @return list<Comment>
     */
    public function findPending(int $limit = 20): array;
}
