<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Annotations\AnnotationReader;
use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Service\Obfuscator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ObfuscatorCompressionPass extends AbstractPass
{
    public function taggedServiceIds(): string { return "obfuscator.compression"; }

    public function classFqcn(): string { return Obfuscator::class; }
    public function addMethod(): string { return "addCompression"; }
}