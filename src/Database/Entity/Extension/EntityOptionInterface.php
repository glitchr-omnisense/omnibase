<?php

namespace Base\Database\Entity\Extension;

interface EntityOptionInterface
{
    public function supports(string $target, ?string $targetValue = null, mixed $object = null): bool;
}