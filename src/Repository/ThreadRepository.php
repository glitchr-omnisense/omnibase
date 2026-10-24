<?php

namespace Base\Repository;

use Base\Entity\Thread;
use Base\Attributes\Traits\HierarchifyTrait;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Enum\WorkflowState;

/**
 * @method Thread|null find($id, $lockMode = null, $lockVersion = null)
 * @method Thread|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Thread[]    findAll()
 * @method Thread[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 *
 * Threads, the interface the code of a site and of the core is given (audit
 * "lesringostars face au master plan", step R15), answered here through the
 * finder: each method runs the query its callers ran by name before.
 *
 * @implements Threads<Thread>
 */
class ThreadRepository extends ServiceEntityRepository implements Threads
{
    use HierarchifyTrait;

    public function oneBySlug(string $slug, bool $cached = true): ?Thread
    {
        return $cached ? $this->cacheOneBySlug($slug) : $this->findOneBySlug($slug);
    }

    public function oneBySlugAndState(?string $slug, array|string $states, array $orderBy = []): ?Thread
    {
        // The finder's WithRoute: the slug only when there is one, as a page's
        // address gives it (no criterion at all on an empty one).
        return $this->cacheOneBySlugWithRouteAndState(['slug' => $slug], 'slug', $states, [], $orderBy);
    }

    public function oneByState(array|string $states, array $orderBy = []): ?Thread
    {
        return $this->cacheOneByState($states, [], $orderBy);
    }

    public function first(array $orderBy): ?Thread
    {
        return $this->cacheOneBy([], $orderBy);
    }

    public function byState(array|string $states, array $orderBy = []): array
    {
        return $this->cacheByState($states, [], $orderBy)->getResult();
    }

    /** @param list<string>|string $states */
    public function byStateUncached(array|string $states): array
    {
        return $this->findByState($states)->getResult();
    }

    public function byStates(array $states): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.state IN (:states)')
            ->setParameter('states', $states)
            ->getQuery()
            ->getResult();
    }

    public function oneByParentAndSlugAndState(?Thread $parent, string $slug, array|string $states, array $orderBy = []): ?Thread
    {
        return $this->cacheOneByParentAndSlugAndState($parent, $slug, $states, [], $orderBy);
    }

    public function byParentAndState(?Thread $parent, array|string $states, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->cacheByParentAndState($parent, $states, [], $orderBy, $limit, $offset)->getResult();
    }

    public function byParentAndStatePublishedWithin(?Thread $parent, array|string $states, string $within, array $orderBy = []): array
    {
        return $this->cacheByParentAndStateAndPublishedAtWithin($parent, $states, $within, [], $orderBy)->getResult();
    }

    public function oneByStateAndSlugStartingWith(array|string $states, string $prefix): ?Thread
    {
        return $this->cacheOneByStateAndSlugStartingWith($states, $prefix);
    }

    public function countByStatePublishedYoungerThan(array|string $states, int|string $age): int
    {
        return $this->countByStateAndPublishedAtYoungerThan($states, $age);
    }

    public function previousOne(array $at, array|string $states, ?string $class = null): ?Thread
    {
        return null === $class
            ? $this->cachePreviousOneAndState($at, $states)
            : $this->cachePreviousOneByClassOfAndState($at, $class, $states);
    }

    public function nextOne(array $at, array|string $states, ?string $class = null): ?Thread
    {
        return null === $class
            ? $this->cacheNextOneAndState($at, $states)
            : $this->cacheNextOneByClassOfAndState($at, $class, $states);
    }

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
