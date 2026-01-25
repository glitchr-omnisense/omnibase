<?php

namespace Base\Database\Entity\Extension;

interface AliasInterface
{
    public function __call(string $method, array $args): mixed;
    public function __isset(string $property): bool;
    public function __get(string $property): mixed;
    public function __set(string $property, mixed $value): void;
}
