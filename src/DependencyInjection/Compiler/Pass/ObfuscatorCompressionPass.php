<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Service\Obfuscator;

class ObfuscatorCompressionPass extends AbstractPass
{
    public function taggedServiceIds(): string
    {
        return 'obfuscator.compression';
    }

    public function classFqcn(): string
    {
        return Obfuscator::class;
    }

    public function addMethod(): string
    {
        return 'addCompression';
    }
}
