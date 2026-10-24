<?php

namespace Base\Repository\User;

use Base\Database\Repository\Entities;
use Base\Entity\User\Connection;
use Base\Entity\User;

/**
 * The accounts' connections (browsers, sessions) (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): ConnectionRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<object> of Connection (its repository declares ServiceEntityRepository<Connection>)
 */
interface Connections extends Entities
{
    /** How many connections an account has. */
    public function countFor(User $user): int;

    /** The connection of a browser's identifier for an account, read from the database. */
    public function oneByUniqidAndUser(string $uniqid, User $user): ?Connection;
}
