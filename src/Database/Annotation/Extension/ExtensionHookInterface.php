<?php

namespace Base\Database\Annotation\Extension;

use Base\Database\Entity\EntityExtensionInterface;

interface ExtensionHookInterface extends EntityExtensionInterface
{
    /**
     * Returns the properties of the extension.
     *
     * @return array
     */
    public function getProperties(): array;
}