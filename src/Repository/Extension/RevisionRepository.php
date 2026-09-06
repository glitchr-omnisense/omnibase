<?php

namespace Base\Repository\Extension;

use Base\Entity\Extension\Revision;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Revision|null find($id, $lockMode = null, $lockVersion = null)
 * @method Revision|null findOneBy(array $criteria, array $orderBy = null)
 * @method Revision[]    findAll()
 * @method Revision[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RevisionRepository extends ServiceEntityRepository
{
    /**
     * History of one entity, newest first.
     *
     * Ordered by id, not createdAt: the id is the insertion sequence and is
     * strictly monotonic, whereas two revisions written in the same second are
     * otherwise unorderable - and #[Timestamp] has been seen to hand out
     * timestamps that disagree with insertion order outright.
     *
     * @return Revision[]
     */
    public function history(string $entityClass, int $entityId, ?int $limit = null): array
    {
        $queryBuilder = $this->createQueryBuilder("r")
            ->andWhere("r.entityClass = :class")->setParameter("class", $entityClass)
            ->andWhere("r.entityId = :id")->setParameter("id", $entityId)
            ->orderBy("r.id", "DESC");

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Ids falling outside the newest $keep revisions of an entity.
     *
     * Returns ids rather than entities so the caller can DELETE them in one
     * statement: trimming runs on every versioned save, and hydrating a
     * history only to discard it would make each save pay for the whole
     * history it is about to shorten.
     *
     * @return int[]
     */
    public function obsoleteIds(string $entityClass, int $entityId, int $keep): array
    {
        if ($keep < 1) {
            return [];
        }

        $ids = $this->createQueryBuilder("r")
            ->select("r.id")
            ->andWhere("r.entityClass = :class")->setParameter("class", $entityClass)
            ->andWhere("r.entityId = :id")->setParameter("id", $entityId)
            ->orderBy("r.id", "DESC")
            ->setFirstResult($keep)
            ->setMaxResults(1000)
            ->getQuery()->getScalarResult();

        return array_map("intval", array_column($ids, "id"));
    }
}
