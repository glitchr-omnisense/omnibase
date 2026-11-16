<?php

namespace Base\Tooling\Composer\Stub;

use Omnibridge\Composer\Stub\AbstractStub;
use Omnibridge\Composer\Stub\StubContext;

/**
 * The App\ stubs of one folder of the core: for each plain class
 * Base\<Name>\X that the application does not define in src/<Name>/, a stub
 * of App\<Name>\X - the alias Base\Service\Model\ClassAliases declares at run
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

    /**
     * The folder's classes wherever they are provided: the core's src/<Name>, then the src/Base/<Name> of every
     * installed package that provides classes under Base\ (a host's or a persistence's package: found by their
     * folder, none is named here).
     */
    public function generate(StubContext $context): iterable
    {
        $files = [];
        foreach ($this->directories($context) as $directory) {
            $this->directory = $directory;
            try {
                $files += (array) parent::generate($context);
            } finally {
                $this->directory = null;
            }
        }
        ksort($files, \SORT_STRING);

        return $files;
    }

    private ?string $directory = null;

    /** @return list<string> */
    private function directories(StubContext $context): array
    {
        $directories = [$context->packageDir.'/src/'.$this->getName()];
        foreach (array_unique([$context->vendorDir, \dirname($context->packageDir, 2)]) as $vendor) {
            foreach (glob($vendor.'/*/*/src/Base/'.$this->getName(), \GLOB_ONLYDIR) ?: [] as $provided) {
                $directories[realpath($provided) ?: $provided] = $provided;
            }
        }

        return array_values($directories);
    }

    protected function getSourceDirectory(StubContext $context): string
    {
        return $this->directory ?? $context->packageDir.'/src/'.$this->getName();
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
