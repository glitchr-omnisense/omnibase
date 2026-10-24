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
    /** The account of a username, through the finder's cache unless $cached is false. */
    public function oneByUsername(string $username, bool $cached = true): ?User;

    /** The account of an address, read from the database (step R28: sign-in, links, OAuth). */
    public function oneByEmail(?string $email): ?User;

    /**
     * The accounts of these ids, through the finder's cache (step R28: the mentions).
     *
     * @param list<int> $ids
     *
     * @return list<User>
     */
    public function byIds(array $ids): array;

    /**
     * The accounts active within the last $seconds, but $exceptId's (step R28:
     * the presence count), read from the database.
     *
     * @return list<User>
     */
    public function activeWithin(int $seconds, ?int $exceptId = null): array;

    /** The account of an id, through the finder's cache (step R28: the profile pages). */
    public function oneById(int $id): ?User;

    /**
     * The accounts holding one of these roles in their own roles (step R28:
     * the notifier's administrators), through the finder's cache.
     *
     * @param list<string>|string $roles
     *
     * @return list<User>
     */
    public function byRoles(array|string $roles): array;

    /**
     * The accounts whose username holds $term, case-insensitive, by username,
     * at most $limit (step R28: the members' search). An application whose
     * accounts have no username gets none.
     *
     * @return list<User>
     */
    public function byUsernameContaining(string $term, int $limit = 50): array;

    /**
     * Other administrators active within the last $onlineDelay seconds, the
     * most recently active first.
     *
     * @return list<User>
     */
    public function findActiveAdmins(int $onlineDelay, ?int $excludeId = null): array;
}
