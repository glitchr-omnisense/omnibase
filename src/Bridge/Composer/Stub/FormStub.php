<?php

namespace Base\Bridge\Composer\Stub;

use Omnibridge\Composer\Stub\StubContext;

/**
 * The stubs of App\Form\ (see AbstractBaseStub): the core's folder, and the form types, extensions
 * and services omnibridge/symfony holds since the plan "omnibase agnostic"'s step F5b
 * (Omnibridge\Symfony\Omnibase\Form\X), which extend them by that name (5.x: by their former
 * names, Base\Form\X, aliased by the core until 6.0).
 */
final class FormStub extends AbstractBaseStub
{
    private const BRIDGE = 'Omnibridge\\Symfony\\Omnibase\\Form';

    private bool $bridge = false;

    public function generate(StubContext $context): iterable
    {
        $files = (array) parent::generate($context);
        $this->bridge = true;
        try {
            $files += (array) parent::generate($context);
        } finally {
            $this->bridge = false;
        }
        ksort($files, \SORT_STRING);

        return $files;
    }

    protected function getSourceNamespace(): string
    {
        return $this->bridge ? self::BRIDGE : parent::getSourceNamespace();
    }

    protected function getSourceDirectory(StubContext $context): string
    {
        if (!$this->bridge) {
            return parent::getSourceDirectory($context);
        }
        foreach ([$context->vendorDir, \dirname($context->packageDir, 2)] as $vendor) {
            if (is_dir($directory = $vendor.'/omnibridge/symfony/src/Omnibase/Form')) {
                return $directory;
            }
        }

        return $context->vendorDir.'/omnibridge/symfony/src/Omnibase/Form';
    }
}
