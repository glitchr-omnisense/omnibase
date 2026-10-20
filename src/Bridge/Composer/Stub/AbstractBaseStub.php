<?php

namespace Base\Bridge\Composer\Stub;

use Omnibridge\Composer\Stub\AbstractStub;
use Omnibridge\Composer\Stub\StubContext;

/**
 * The App\ stubs of one folder of the core: for each plain class
 * Base\<Name>\X that the application does not define in src/<Name>/, a stub
 * of App\<Name>\X - the alias Base\Bundle\AbstractBaseBundle declares at run
 * time - for an IDE and static analysis.
 *
 * Run by omnibridge/composer alone, on post-autoload-dump (the core declares
 * these providers in its composer.json, extra.omnibridge.stubs): never loaded
 * by an application, hence outside the Foundation and its bare check. The
 * stubs land in vendor/composer/omnibridge/stubs/<Name>/, which no autoloader
 * reads.
 */
abstract class AbstractBaseStub extends AbstractStub
{
    /** Entity for EntityStub: the folder of src/ and the namespace segment. */
    public function getName(): string
    {
        return preg_replace('/Stub$/', '', substr(static::class, strrpos(static::class, '\\') + 1));
    }

    protected function getSourceNamespace(): string
    {
        return 'Base\\'.$this->getName();
    }

    protected function getSourceDirectory(StubContext $context): string
    {
        return $context->packageDir.'/src/'.$this->getName();
    }

    protected function getTargetNamespace(): string
    {
        return 'App\\'.$this->getName();
    }

    protected function getApplicationDirectory(StubContext $context): ?string
    {
        return $context->projectDir.'/src/'.$this->getName();
    }
}
