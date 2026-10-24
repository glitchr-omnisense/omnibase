<?php

namespace Base\Repository\Extension;

use Base\Database\Repository\Entities;
use Base\Entity\Extension\Revision;

/**
 * The entities' revisions (audit "lesringostars face au master plan", step
 * R28; see Base\Database\Repository\Entities): an entity's history, as
 * VersionManager reads it - RevisionRepository's query.
 *
 * @extends Entities<Revision>
 */
interface Revisions extends Entities
{
    /** @return list<Revision> the newest first */
    public function history(string $entityClass, int $entityId, ?int $limit = null): array;
}
