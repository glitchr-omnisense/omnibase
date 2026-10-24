<?php

namespace Base\Repository;

use Base\Entity\Thread;
use Base\Entity\ThreadIntl;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method ThreadIntl|null find($id, $lockMode = null, $lockVersion = null)
 * @method ThreadIntl|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method ThreadIntl[]    findAll()
 * @method ThreadIntl[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class ThreadIntlRepository extends ServiceEntityRepository implements ThreadTranslations
{
    public function search(array $terms, array $states = [], Thread|int|string|null $parent = null): array
    {
        $terms = array_filter(
            array_intersect_key($terms, ["title" => true, "excerpt" => true, "content" => true]),
            fn($term) => is_string($term) && trim($term) !== ""
        );
        if (!$terms) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder("i")->join("i.translatable", "t");
        $expr = [];
        foreach ($terms as $field => $term) {
            $expr[] = "LOWER(i." . $field . ") LIKE :term_" . $field;
            $queryBuilder->setParameter("term_" . $field, "%" . mb_strtolower(trim($term)) . "%");
        }
        $queryBuilder->andWhere(implode(" OR ", $expr));

        if ($states) {
            $queryBuilder->andWhere("t.state IN (:states)")->setParameter("states", $states);
        }
        if ($parent) {
            $queryBuilder->andWhere("t.parent = :parent")->setParameter("parent", $parent);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
