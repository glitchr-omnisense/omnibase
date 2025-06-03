<?php

namespace Base\Database\Entity\Extension;
use Symfony\Component\PropertyAccess\Exception\AccessException;

trait AliasTrait
{
    public function __call($method, $args): mixed
    {
        throw new AccessException('This method is not implemented. Use the Alias annotation instead.');
    }
}