<?php

namespace Base\Repository\User;

use Base\Database\Repository\Entities;
use Base\Entity\User\Identity;

/**
 * The accounts' identities at providers (plan "omnibase agnostic", step F2d; see
 * Base\Database\Repository\Entities): IdentityRepository answers it with the
 * queries Base\Auth\UserIdentities ran on it by name before.
 *
 * @extends Entities<Identity>
 */
interface Identities extends Entities
{
    public function findOneBySubject(string $provider, string $subject): ?Identity;

    /** @return list<Identity> the account's identities, by provider */
    public function findOfUser(object $user): array;
}
