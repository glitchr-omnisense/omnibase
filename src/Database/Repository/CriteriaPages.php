<?php

namespace Base\Database\Repository;

use Base\Service\Model\Pages;

/**
 * The entities of a repository matching criteria, as pages: counted by the database (count()), one page read at a
 * time (findBy() with its limit and offset), the same criteria for both - what a list pages through on any
 * persistence (Pagination, a back-office index, a forum's topics).
 *
 * Built by Base\\Service\\Paginator from the repository, the criteria and the order it is given: a site or a bundle calls
 * the Paginator, never this class.
 *
 * @internal
 */
final class CriteriaPages implements Pages
{
    private ?int $count = null;

    /**
     * @param Entities<object>           $entities
     * @param array<int|string, mixed>   $criteria findBy()'s: fields, lists, Finder\Key, Finder\Group
     * @param array<string, string>|null $orderBy  field or one-association path => ASC|DESC
     */
    public function __construct(private readonly Entities $entities, private readonly array $criteria = [], private readonly ?array $orderBy = null)
    {
    }

    public function count(): int
    {
        return $this->count ??= $this->entities->count($this->criteria);
    }

    public function slice(int $offset, int $limit): array
    {
        return $this->entities->findBy($this->criteria, $this->orderBy, $limit, $offset);
    }
}
