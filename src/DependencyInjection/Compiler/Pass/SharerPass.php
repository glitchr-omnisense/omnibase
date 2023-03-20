<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Annotations\AnnotationReader;
use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Service\Sharer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SharerPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return "base.service.sharer";
    }

    public function classFqcn(): string
    {
        return Sharer::class;
    }
    public function addMethod(): string
    {
        return "addAdapter";
    }
}
