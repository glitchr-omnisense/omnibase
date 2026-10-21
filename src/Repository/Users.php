<?php

namespace Base\Repository;

use Base\Database\Repository\Entities;
use Base\Entity\User;

/**
 * The accounts (audit "lesringostars face au master plan", step R15; see
 * Base\Database\Repository\Entities). UserRepository answers it - an
 * application's App\Repository\UserRepository with it -, besides what it
 * declares to Security (Base\Contract\SecurityUserLoader and
 * SecurityUserProvider, R2).
 *
 * @extends Entities<User>
 */
interface Users extends Entities
{
    public function oneByUsername(string $username): ?User;

    /**
     * Other administrators active within the last $onlineDelay seconds, the
     * most recently active first.
     *
     * @return list<User>
     */
    public function findActiveAdmins(int $onlineDelay, ?int $excludeId = null): array;
}
