<?php

namespace Base\Repository;

use Base\Database\Repository\Entities;
use Base\Entity\Thread;
use Base\Entity\ThreadIntl;

/**
 * The threads' translations, as the site-wide search reads them (audit
 * "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): ThreadIntlRepository's query, which
 * the core's ThreadSearchController and an application's search (lesringostars'
 * App\Action\Search\Results) wrote by hand on a query builder before.
 *
 * @extends Entities<ThreadIntl>
 */
interface ThreadTranslations extends Entities
{
    /**
     * The translations whose fields hold their term, any of them - (title OR
     * excerpt OR content), each compared lower-cased with LIKE '%term%' -,
     * of threads in one of $states (any when empty), under $parent when given.
     * No term, no translation (not LIKE '%%', the whole site).
     *
     * @param array<string, mixed>   $terms  field (title, excerpt, content) => term; empty or non-text terms are left out
     * @param list<string>           $states
     * @param Thread|int|string|null $parent the parent thread or its id
     *
     * @return list<ThreadIntl>
     */
    public function search(array $terms, array $states = [], Thread|int|string|null $parent = null): array;
}
