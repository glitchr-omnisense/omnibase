<?php

namespace Base\Repository\User;

use Base\Database\Repository\Entities;
use Base\Entity\User\PushSubscription;

/**
 * The browsers subscribed to Web Push (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): PushSubscriptionRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<PushSubscription>
 */
interface PushSubscriptions extends Entities
{
    /** How many accounts have at least one subscribed browser. */
    public function countDistinctUsers(): int;
}
