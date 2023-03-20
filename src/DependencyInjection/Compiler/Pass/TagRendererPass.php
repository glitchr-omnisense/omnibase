<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Annotations\AnnotationReader;
use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Twig\Environment;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TagRendererPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return "twig.tag_renderer";
    }

    public function classFqcn(): string
    {
        return Environment::class;
    }
    public function addMethod(): string
    {
        return "addRenderer";
    }
}
