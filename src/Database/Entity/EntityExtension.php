<?php

namespace Base\Database\Entity;

/**
 *
 */
class EntityExtension
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_REMOVE = 'remove';

    protected array $extensions = [];

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function addExtension(EntityExtensionInterface $extension): self
    {
        $this->extensions[get_class($extension)] = $extension;
        return $this;
    }
}
