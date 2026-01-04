<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Database\Entity\EntityExtension;
use Base\Database\Entity\EntityExtensionInterface;
use Base\DependencyInjection\Compiler\AbstractPass;
use Dom\Entity;

/**
 *
 */
class EntityExtensionPass extends AbstractPass
{
    public function serviceInterface(): ?string 
    { 
        return EntityExtensionInterface::class;
    }

    public function taggedServiceIds(): ?string
    {
        return null;
    }

    public function classFqcn(): string
    {
        return EntityExtension::class;
    }

    public function addMethod(): string
    {
        return 'addExtension';
    }
}
