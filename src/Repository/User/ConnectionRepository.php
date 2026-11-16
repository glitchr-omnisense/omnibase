<?php

namespace Base\Repository\User;

use App\Entity\User;
use Base\Entity\User\Connection;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Repository\User\Connections;

/**
 * @extends ServiceEntityRepository<Connection>
 *
 * @method Connection|null find($id)
 * @method Connection|null findOneBy(array $criteria, array $orderBy = null)
 * @method Connection[]    findAll()
 * @method Connection[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConnectionRepository extends ServiceEntityRepository implements Connections
{
    /** Connections (step R28): ConnectionTracker's finder, its arguments as it gave them. */
    public function oneByUniqidAndUser(string $uniqid, \Base\Entity\User $user): ?\Base\Entity\User\Connection
    {
        return $this->findOneByUniqidAndUser($uniqid, ["user" => $user]);
    }

    /** How many browsers the account was signed into from, counted by the database. */
    public function countFor(\Base\Entity\User $user): int
    {
        return $this->count(['user' => $user]);
    }
}
