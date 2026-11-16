<?php

namespace Base\Repository\Extension;

use Base\Entity\Extension\TrashBall;
use Base\Database\Repository\ServiceEntityRepository;
use DateTime;
use DateTimeInterface;
use Base\Repository\Extension\TrashBalls;

/**
 * @method TrashBall|null find($id)
 * @method TrashBall|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrashBall[]    findAll()
 * @method TrashBall[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrashBallRepository extends ServiceEntityRepository implements TrashBalls
{
    /**
     * Everything currently in the trash, newest first.
     *
     * @return TrashBall[]
     */
    public function contents(?string $entityClass = null, ?int $limit = null, int $offset = 0): array
    {
        return $this->findBy(null === $entityClass ? [] : ['entityClass' => $entityClass], ['id' => 'DESC'], $limit, $offset);
    }

    public function countContents(?string $entityClass = null): int
    {
        return $this->count(null === $entityClass ? [] : ['entityClass' => $entityClass]);
    }

    /**
     * Trash entries whose grace period has run out - what the purge destroys.
     *
     * @return TrashBall[]
     */
    public function expired(?DateTimeInterface $now = null, ?int $limit = null): array
    {
        // permanentAfter <= now, which no row without a date meets.
        return $this->findByPermanentAfterLowerEqualTo($now ?? new DateTime(), [], ['permanentAfter' => 'ASC'], $limit)->getResult();
    }

    /**
     * The trash entry standing for one entity, if it is in the trash.
     */
    public function forEntity(string $entityClass, int $entityId): ?TrashBall
    {
        return $this->findOneBy(['entityClass' => $entityClass, 'entityId' => $entityId], ['id' => 'DESC']);
    }
}
