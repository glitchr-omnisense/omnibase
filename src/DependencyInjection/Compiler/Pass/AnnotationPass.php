<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Annotations\AnnotationReader;
use Base\DependencyInjection\Compiler\AbstractPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Registry;

class AnnotationPass extends AbstractPass
{
    public function taggedServiceIds(): string { return "base.annotation"; }

    public function classFqcn(): string { return AnnotationReader::class; }
    public function addMethod(): string { return "addAnnotation"; }
}
