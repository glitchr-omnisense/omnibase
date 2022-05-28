<?php

namespace Base\Database\Factory\AggregateHydrator;

interface PopulableInterface
{
    public function populate(array $data = []);
}