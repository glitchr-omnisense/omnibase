<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

use App\Entity\Marketplace\Product;
use App\Entity\Marketplace\Product\Taxon;
use App\Entity\Marketplace\Sales\Attribute\Scope\TaxonAdapterAbstract;

interface ScopeAdapterInterface extends AttributeAdapterInterface
{
    public function supports(mixed $value): bool;
    public function contains(mixed $value, mixed $subject): bool;
}