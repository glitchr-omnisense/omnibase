<?php

namespace Base\Service\Model;

use App\Entity\Thread;

/**
 *
 */
interface PaginationInterface
{
    public function getPage();

    public function getPageSize();

    public function getPageRange();

    public function getTotalPages();

    public function getTemplate();

    public function getPath(string $name, int $page = 0, array $parameters = []);

    public function getResult();

    public function getTotalCount();
}
