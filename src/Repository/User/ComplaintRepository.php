<?php

namespace Base\Repository\User;

use Base\Entity\User\Complaint;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Complaint|null find($id, $lockMode = null, $lockVersion = null)
 * @method Complaint|null findOneBy(array $criteria, array $orderBy = null)
 * @method Complaint[]    findAll()
 * @method Complaint[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComplaintRepository extends ServiceEntityRepository
{
    /**
     * The newest complaints, of one status (and one source) or all.
     *
     * @return Complaint[]
     */
    public function newest(?string $status = null, ?string $source = null, int $limit = 200): array
    {
        $query = $this->createQueryBuilder("c")->orderBy("c.createdAt", "DESC")->setMaxResults($limit);
        if (null !== $status) {
            $query->andWhere("c.status = :status")->setParameter("status", $status);
        }
        if (null !== $source) {
            $query->andWhere("c.source = :source")->setParameter("source", $source);
        }

        return $query->getQuery()->getResult();
    }
}
