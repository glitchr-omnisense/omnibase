<?php

namespace Base\Service;

use Base\Model\Breadcrumb;

interface BreadgrinderInterface
{
    public function grind(string $name): Breadcrumb;
}
