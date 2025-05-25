<?php

namespace Base\Database\Annotation\Extension;

use Base\Database\Entity\EntityExtensionInterface;

interface EntityInlineInterface extends EntityExtensionInterface
{
    /**
     * Returns the properties of the entity.
     *
     * @return array
     */
    public function getProperties(): array;
}