<?php

namespace Base\Repository\User;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\User\Identity;

/**
 * The accounts' identities at providers (Base\Entity\User\Identity, the
 * userIdentity table); omniauth reads them through Base\Auth\UserIdentities.
 *
 * @method Identity|null find($id, $lockMode = null, $lockVersion = null)
 * @method Identity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Identity[]    findAll()
 * @method Identity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IdentityRepository extends ServiceEntityRepository
{
    public function findOneBySubject(string $provider, string $subject): ?Identity
    {
        return $this->createQueryBuilder('identity')
            ->where('identity.provider = :provider')
            ->andWhere('identity.subject = :subject')
            ->setParameter('provider', $provider)
            ->setParameter('subject', $subject)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Identity> the account's identities, by provider */
    public function findOfUser(object $user): array
    {
        return $this->createQueryBuilder('identity')
            ->where('identity.user = :user')
            ->setParameter('user', $user)
            ->orderBy('identity.provider')
            ->getQuery()
            ->getResult();
    }
}
