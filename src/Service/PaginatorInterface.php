<?php

namespace Base\Service;

use Base\Database\Repository\Entities;
use Base\Service\Model\Pages;
use Base\Service\Model\Pagination;

/**
 * THE pagination of omnibase, on any persistence: one call takes what is paged - a repository (Entities, with the
 * finder criteria and the order), a finder's Result (what findBy...() hands back), or an array of rows - and the page
 * asked, and gives the page ready for the template (Pagination: the rows, the total, the pages' links).
 *
 *     $page = $paginator->paginate($articles, ['state' => 'publish', Group::of(...)], ['publishedAt' => 'DESC'], $request->query->getInt('page', 1));
 *     $page = $paginator->paginate($articles->findByState('publish', [], ['publishedAt' => 'DESC']), page: 2);
 *     $page = $paginator->paginate($rows, pageSize: 10);
 *
 * Nothing of an ORM: the rows are counted and sliced by the persistence through the repository's own findBy() and
 * count(), or by the finder's Result.
 */
interface PaginatorInterface
{
    /**
     * @param Entities<object>|Pages|array<int|string, mixed> $source   a repository, a finder's Result, or the rows
     * @param array<int|string, mixed>                        $criteria findBy()'s criteria, for a repository (none for a Result or an array)
     * @param array<string, string|list<mixed>>|null          $orderBy  findBy()'s order, for a repository
     * @param int                                             $page     the page asked, from 1
     * @param int                                             $pageSize rows per page (0: base.paginator.page_size)
     * @param int                                             $pageRange pages linked around the current one (0: base.paginator.page_range)
     */
    public function paginate(Entities|Pages|array $source, array $criteria = [], ?array $orderBy = null, int $page = 1, int $pageSize = 0, int $pageRange = 0): Pagination;
}
