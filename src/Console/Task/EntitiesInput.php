<?php

namespace Base\Console\Task;

/** icon:entities' and autocomplete:entities' options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class EntitiesInput
{
    /**
     * @param string  $source the application's App\ folder (Symfony: ./src)
     * @param ?string $entity one entity's rows (its class), the candidate classes otherwise
     * @param ?string $id     one row of it
     */
    public function __construct(public string $source = 'src', public ?string $entity = null, public ?string $id = null)
    {
    }
}
