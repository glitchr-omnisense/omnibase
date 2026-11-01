<?php

namespace Base\Bridge\Doctrine;

use Base\Database\Walker\CountWalker;
use Base\Database\Walker\GroupByWalker;
use Base\Database\Walker\TranslatableWalker;
use Base\Service\Model\Pages;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * A DQL query paged (plan "omnibase agnostic", step F2e): what Base\Service\Model\Pagination did with a
 * Query, moved here as it was - the rows grouped by id (GroupByWalker) through Doctrine's Paginator, the
 * translations joined (TranslatableWalker), the total counted by CountWalker on a clone. Moves to
 * omnibridge/doctrine at F3.
 */
final class QueryPages implements Pages
{
    private readonly Paginator $paginator;
    private ?int $count = null;

    public function __construct(private readonly Query $query)
    {
        $paged = self::copy($query, false);
        $paged->setHint(Query::HINT_CUSTOM_TREE_WALKERS, [GroupByWalker::class]);
        $paged->setHint(GroupByWalker::HINT_GROUP_ARRAY, ['id']);
        $paged->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TranslatableWalker::class);
        $this->paginator = new Paginator($paged);
    }

    public function count(): int
    {
        return $this->count ??= (int) self::countQuery($this->query, false)->getSingleScalarResult();
    }

    public function slice(int $offset, int $limit): array
    {
        return $this->query($offset, $limit)->getResult();
    }

    /** The paged query of that page (Pagination::getQuery()). */
    public function query(int $offset, int $limit): Query
    {
        return $this->paginator->getQuery()->setFirstResult($offset)->setMaxResults($limit);
    }

    public function paginator(): Paginator
    {
        return $this->paginator;
    }

    /** The query counting the rows of $query (Pagination::getCountQuery()). */
    public static function countQuery(Query $query, bool $bHint = true): Query
    {
        $clone = self::copy($query, $bHint);
        $clone->setCacheable(false);
        $clone->setHint(Query::HINT_CUSTOM_TREE_WALKERS, [CountWalker::class]);
        $clone->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TranslatableWalker::class);

        return $clone;
    }

    private static function copy(Query $query, bool $bHint = true): Query
    {
        $clone = clone $query;
        foreach ($query->getParameters() as $parameter) {
            $clone->setParameter($parameter->getName(), $parameter->getValue());
        }
        if ($bHint) {
            foreach ($query->getHints() as $hint => $class) {
                $clone->setHint($hint, $class);
            }
        }

        return $clone;
    }
}
