<?php

namespace Base\Repository\User;

use App\Entity\User;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\User\Notification;

/**
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    /**
     * A real COUNT. Not the inherited count()/countBy(): base-bundle's
     * ServiceEntityRepository redefines count() to return an array and
     * countBy() hydrates every row, and this runs on every page for the
     * toolbar badge.
     */
    public function countUnreadFor(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')->setParameter('user', $user)
            ->andWhere('n.isRead = false')
            ->getQuery()->getSingleScalarResult();
    }
}
