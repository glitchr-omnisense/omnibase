<?php

namespace Base\Repository\User;

use Base\Database\Repository\Entities;
use Base\Entity\User\Complaint;

/**
 * The complaints (reports) of the members (plan "omnibase agnostic", step E2'; see
 * Base\Database\Repository\Entities): what omnibase/admin's dashboard tile asks,
 * ComplaintRepository's answer.
 *
 * @extends Entities<Complaint>
 */
interface Complaints extends Entities
{
    /**
     * The newest complaints, of one status (and one source) or all.
     *
     * @return list<Complaint>
     */
    public function newest(?string $status = null, ?string $source = null, int $limit = 200): array;
}
