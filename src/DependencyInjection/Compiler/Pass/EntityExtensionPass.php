<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Database\Entity\EntityExtension;
use Base\DependencyInjection\Compiler\AbstractPass;

class EntityExtensionPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return 'base.entity_extension';
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
