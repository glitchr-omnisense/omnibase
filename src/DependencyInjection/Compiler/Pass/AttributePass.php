<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Attributes\AttributeReader;
use Base\DependencyInjection\Compiler\AbstractPass;

class AttributePass extends AbstractPass
{
    public function serviceInterface(): ?string 
    { 
        return null;
    }

    public function taggedServiceIds(): ?string
    {
        return 'base.attribute';
    }

    public function classFqcn(): string
    {
        return AttributeReader::class;
    }

    public function addMethod(): string
    {
        return 'addAttribute';
    }
}
