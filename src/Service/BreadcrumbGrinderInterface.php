<?php

namespace Base\Service;

use Base\Model\Breadcrumb;

interface BreadcrumbGrinderInterface
{
    public function create(string $name): Breadcrumb;
    public function has(string $name): bool;
    public function get(string $name): ?Breadcrumb;
}
