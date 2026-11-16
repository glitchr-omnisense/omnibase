<?php

namespace Base\Repository\Thread;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Thread;
use Base\Entity\Thread\Comment;
use Base\Enum\CommentState;
use Base\Repository\Thread\Comments;
use Base\Database\Repository\Finder\Key;

/**
 * @extends ServiceEntityRepository<Comment>
 *
 * @method Comment|null find($id)
 * @method Comment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Comment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentRepository extends ServiceEntityRepository implements Comments
{
    /**
     * The comments online under a thread - or the visitors' book when
     * $thread is null -, the top-level ones, newest first; their replies
     * hang under each (Comment::getVisibleReplies()).
     *
     * @return list<Comment>
     */
    public function findVisible(?Thread $thread, int $limit = 200): array
    {
        return $this->findBy($this->visible($thread) + ['parent' => Key::of(null)], ['createdAt' => 'DESC'], $limit);
    }

    public function countVisible(?Thread $thread): int
    {
        return $this->count($this->visible($thread));
    }

    /**
     * The criteria of the comments online under a thread, the visitors' book for null (step E2b):
     * the thread compared by its key - `c.thread = :thread`, `c.thread IS NULL` - and not through a
     * join, where a persistence puts Thread's trash scope: a comment under a trashed thread would read
     * as one of the visitors' book.
     *
     * @return array<string, mixed>
     */
    private function visible(?Thread $thread): array
    {
        return ['state' => CommentState::APPROVED, 'thread' => Key::of($thread)];
    }

    public function countPending(): int
    {
        return $this->count(['state' => CommentState::PENDING]);
    }

    /** @return list<Comment> */
    public function findPending(int $limit = 20): array
    {
        return $this->findBy(['state' => CommentState::PENDING], ['createdAt' => 'ASC'], $limit);
    }

    /** The last comment this address left, to hold the flood interval. */
    public function findLastFromIp(string $ip): ?Comment
    {
        return $this->findOneBy(['ip' => $ip], ['createdAt' => 'DESC']);
    }
}
