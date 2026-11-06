<?php

namespace Base\Console\Task;

use Base\Console\Report;

/** entity:discriminator (plan "omnibase agnostic", step F5e, slice 3): a class that is no entity refused aloud. */
final class EntityDiscriminator extends EntitiesTask
{
    public function run(EntityDiscriminatorInput $input, ?\Closure $sink = null): Report
    {
        if (!$this->isEntity($input->entity)) {
            throw new \Exception('Class "'.$input->entity.'" is not an entity.');
        }

        return new Report($sink);
    }
}
