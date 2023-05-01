<?php

namespace Base\Database\Entity\AggregateHydrator;

/**
 *
 */
interface SerializableInterface
{
    public function exchangeArray(array|object $array): array;

    public function getArrayCopy(): array;
}
