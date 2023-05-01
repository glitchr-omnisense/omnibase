<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Service\IconProvider;

/**
 *
 */
class IconProviderPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return 'base.service.icon';
    }

    public function classFqcn(): string
    {
        return IconProvider::class;
    }

    public function addMethod(): string
    {
        return 'addAdapter';
    }
}
