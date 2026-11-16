<?php

namespace Base\Repository\User;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\User\Identity;
use Base\Repository\User\Identities;

/**
 * The accounts' identities at providers (Base\Entity\User\Identity, the
 * userIdentity table); omniauth reads them through Base\Auth\UserIdentities.
 *
 * @method Identity|null find($id)
 * @method Identity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Identity[]    findAll()
 * @method Identity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<Identity>
 */
class IdentityRepository extends ServiceEntityRepository implements Identities
{
    public function findOneBySubject(string $provider, string $subject): ?Identity
    {
        return $this->findOneBy(['provider' => $provider, 'subject' => $subject]);
    }

    /** @return list<Identity> the account's identities, by provider */
    public function findOfUser(object $user): array
    {
        return $this->findBy(['user' => $user], ['provider' => 'ASC']);
    }
}
