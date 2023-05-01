<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Service\Sharer;

class SharerPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return 'base.service.sharer';
    }

    public function classFqcn(): string
    {
        return Sharer::class;
    }

    public function addMethod(): string
    {
        return 'addAdapter';
    }
}
