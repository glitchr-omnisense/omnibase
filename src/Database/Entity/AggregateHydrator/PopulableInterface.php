<?php

namespace Base\Database\Entity\AggregateHydrator;

/**
 *
 */
interface PopulableInterface
{
    public function populate(array $data = []);
}
