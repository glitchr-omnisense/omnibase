<?php

namespace Base\Repository\Layout;

use Base\Database\Repository\Entities;
use Base\Entity\Layout\Semantic;

/**
 * The semantic links of the texts (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): SemanticRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<Semantic>
 */
interface Semantics extends Entities
{
    /**
     * Every semantic link, through the finder's cache.
     *
     * @return list<Semantic>
     */
    public function every(): array;
}
