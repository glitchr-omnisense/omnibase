<?php

namespace Base\Repository\Extension;

use Base\Database\Repository\Entities;
use Base\Entity\Extension\TrashBall;

/**
 * The trash (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): what TrashManager and the purge ask of
 * it, TrashBallRepository's queries.
 *
 * @extends Entities<TrashBall>
 */
interface TrashBalls extends Entities
{
    /** @return list<TrashBall> the newest first, of one class when given */
    public function contents(?string $entityClass = null, ?int $limit = null, int $offset = 0): array;

    public function countContents(?string $entityClass = null): int;

    /** @return list<TrashBall> past their grace period at $now (now when null), the oldest first */
    public function expired(?\DateTimeInterface $now = null, ?int $limit = null): array;
}
