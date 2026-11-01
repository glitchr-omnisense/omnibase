<?php

namespace Base\Service\Model;

/**
 * Rows to page through, counted and sliced by whatever runs the query (plan
 * "omnibase agnostic", step F2e): what Base\Service\Model\Pagination asks in
 * place of Doctrine's Query and Paginator. Doctrine's is
 * Base\Bridge\Doctrine\QueryPages (a DQL query, with omnibase's walkers); a
 * plain array is paged by Pagination itself.
 */
interface Pages
{
    /** How many rows in all. */
    public function count(): int;

    /**
     * The rows of one page.
     *
     * @return list<mixed>
     */
    public function slice(int $offset, int $limit): array;
}
