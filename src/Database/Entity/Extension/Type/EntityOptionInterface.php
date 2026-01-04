<?php

namespace Base\Database\Entity\Extension\Type;

interface EntityOptionInterface
{
    public function supports(string $target, ?string $targetValue = null, mixed $object = null): bool;
}