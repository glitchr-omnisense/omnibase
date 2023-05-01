<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Annotations\AnnotationReader;
use Base\DependencyInjection\Compiler\AbstractPass;

/**
 *
 */
class AnnotationPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return 'base.annotation';
    }

    public function classFqcn(): string
    {
        return AnnotationReader::class;
    }

    public function addMethod(): string
    {
        return 'addAnnotation';
    }
}
