<?php

namespace Base\Repository\Extension;

use Base\Entity\Extension\TrashBall;
use Base\Database\Repository\ServiceEntityRepository;
use DateTime;
use DateTimeInterface;

/**
 * @method TrashBall|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrashBall|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrashBall[]    findAll()
 * @method TrashBall[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrashBallRepository extends ServiceEntityRepository
{
    /**
     * Everything currently in the trash, newest first.
     *
     * @return TrashBall[]
     */
    public function contents(?string $entityClass = null, ?int $limit = null, int $offset = 0): array
    {
        $queryBuilder = $this->createQueryBuilder("t")
            ->orderBy("t.id", "DESC")
            ->setFirstResult($offset);

        if ($entityClass !== null) {
            $queryBuilder->andWhere("t.entityClass = :class")->setParameter("class", $entityClass);
        }

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function countContents(?string $entityClass = null): int
    {
        $queryBuilder = $this->createQueryBuilder("t")->select("COUNT(t.id)");

        if ($entityClass !== null) {
            $queryBuilder->andWhere("t.entityClass = :class")->setParameter("class", $entityClass);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * Trash entries whose grace period has run out - what the purge destroys.
     *
     * @return TrashBall[]
     */
    public function expired(?DateTimeInterface $now = null, ?int $limit = null): array
    {
        $queryBuilder = $this->createQueryBuilder("t")
            ->andWhere("t.permanentAfter IS NOT NULL")
            ->andWhere("t.permanentAfter <= :now")->setParameter("now", $now ?? new DateTime())
            ->orderBy("t.permanentAfter", "ASC");

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * The trash entry standing for one entity, if it is in the trash.
     */
    public function forEntity(string $entityClass, int $entityId): ?TrashBall
    {
        return $this->createQueryBuilder("t")
            ->andWhere("t.entityClass = :class")->setParameter("class", $entityClass)
            ->andWhere("t.entityId = :id")->setParameter("id", $entityId)
            ->orderBy("t.id", "DESC")
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
