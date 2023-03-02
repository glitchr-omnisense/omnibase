<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Annotations\AnnotationReader;
use Base\Database\Entity\EntityExtension;
use Base\DependencyInjection\Compiler\AbstractPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class EntityExtensionPass extends AbstractPass
{
    public function taggedServiceIds(): string { return "base.entity_extension"; }

    public function classFqcn(): string { return EntityExtension::class; }
    public function addMethod(): string { return "addExtension"; }
}
