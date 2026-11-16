<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Semantic;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Repository\Layout\Semantics;

/**
 * @method Semantic|null find($id)
 * @method Semantic|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Semantic[]    findAll()
 * @method Semantic[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class SemanticRepository extends ServiceEntityRepository implements Semantics
{
    /** Semantics (step R28): SemanticEnhancer's finder. */
    public function every(): array
    {
        return $this->cacheAll()->getResult();
    }

}
