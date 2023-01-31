<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Annotations\AnnotationReader;
use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Service\IconProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class IconProviderPass extends AbstractPass
{
    public function taggedServiceIds(): string { return "base.service.icon"; }

    public function classFqcn(): string { return IconProvider::class; }
    public function addMethod(): string { return "addAdapter"; }
}