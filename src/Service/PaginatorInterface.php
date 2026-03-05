<?php

namespace Base\Service;

use Doctrine\ORM\Query;

interface PaginatorInterface
{
    public function paginate(Query $query, int $page = 0, int $pageSize = 0, int $pageRange = 0);
}
