<?php

namespace Base\Database\Factory\AggregateHydrator;

interface SerializableInterface
{
    public function exchangeArray(array|object $array): array;
    public function getArrayCopy(): array;
}