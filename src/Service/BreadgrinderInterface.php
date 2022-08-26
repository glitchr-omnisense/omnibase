<?php

namespace Base\Service;

use Base\Service\Model\Breadcrumb;

interface BreadgrinderInterface
{
    public function grind(string $name): Breadcrumb;
}
