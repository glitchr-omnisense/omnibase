<?php

namespace Base\Console\Task;

/** uploader:entities' options (plan "omnibase agnostic", step F5e, slice 3). */
readonly class UploaderEntitiesInput
{
    /**
     * @param string  $source the application's App\ folder (Symfony: <project>/src)
     * @param ?string $entity an entity's name under App\Entity\ or Base\Entity\ (either prefix taken off), none: all
     */
    public function __construct(
        public string $source = 'src',
        public ?string $entity = null,
        public ?string $property = null,
        public ?string $uuid = null,
        public bool $orphans = false,
        public bool $deleteOrphans = false,
    ) {
    }

    /** The entity's name without its App\Entity\ or Base\Entity\ prefix ("" for all). */
    public function entityName(): string
    {
        return (string) str_strip($this->entity, ['App\\Entity\\', 'Base\\Entity\\']);
    }
}
