<?php

namespace Base\Database\Entity\Extension;

/**
 *
 */
interface AliasInterface
{
    public function __call(string $method, array $args): mixed;
}
