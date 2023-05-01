<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Twig\Environment;

class TagRendererPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return 'twig.tag_renderer';
    }

    public function classFqcn(): string
    {
        return Environment::class;
    }

    public function addMethod(): string
    {
        return 'addRenderer';
    }
}
