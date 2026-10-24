<?php

namespace Base\Repository\Layout;

use Base\Database\Repository\Entities;
use Base\Entity\Layout\Redirection;

/**
 * The redirections (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): RedirectionRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<Redirection>
 */
interface Redirections extends Entities
{
    /** The redirection that answers this address, if any. */
    public function forAddress(string $address): ?Redirection;

    /** Counts a use of the redirection. */
    public function hit(Redirection $redirection): void;
}
