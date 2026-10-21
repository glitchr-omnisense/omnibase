<?php

namespace Base\Repository\User;

use App\Entity\User;
use Base\Database\Repository\Entities;
use Base\Entity\User\Notification;

/**
 * A user's notifications, those with something to show (audit "lesringostars
 * face au master plan", step R15; see Base\Database\Repository\Entities).
 *
 * @extends Entities<Notification>
 */
interface Notifications extends Entities
{
    public function countUnreadFor(User $user): int;

    /** @return list<Notification> newest first */
    public function findVisibleFor(User $user, int $limit = 100, int $offset = 0, bool $unreadOnly = false): array;
}
