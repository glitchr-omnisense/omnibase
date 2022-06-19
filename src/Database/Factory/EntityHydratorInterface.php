<?php

namespace Base\Database\Factory;

interface EntityHydratorInterface {

    public function hydrate(mixed $entity, null|array|object $data = [], array $fieldExceptions = [], int $aggregateModel = EntityHydrator::DEFAULT_AGGREGATE, ...$constructArguments): mixed;
}