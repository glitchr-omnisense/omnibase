<?php

namespace Base\Bridge\Composer\Stub;

use Omnibridge\Composer\Stub\StubContext;

/**
 * The stubs of App\Repository\ (see AbstractBaseStub). The core's repositories are
 * omnibridge/doctrine's since the plan "omnibase agnostic"'s step F3
 * (Omnibridge\Doctrine\Omnibase\Repository\X): their stubs extend them by their former
 * names, Base\Repository\X, which the core aliases (src/Resources/former-names.php) and
 * the application's App\Repository\X stands for at run time, as before.
 */
final class RepositoryStub extends AbstractBaseStub
{
    private const SOURCE = 'Omnibridge\\Doctrine\\Omnibase\\Repository';

    protected function getSourceNamespace(): string
    {
        return self::SOURCE;
    }

    protected function getSourceDirectory(StubContext $context): string
    {
        foreach ([$context->vendorDir, \dirname($context->packageDir, 2)] as $vendor) {
            if (is_dir($directory = $vendor.'/omnibridge/doctrine/src/Omnibase/Repository')) {
                return $directory;
            }
        }

        return $context->vendorDir.'/omnibridge/doctrine/src/Omnibase/Repository';
    }

    protected function render(string $namespace, string $name, string $class): string
    {
        return parent::render($namespace, $name, 'Base\\Repository'.substr($class, \strlen(self::SOURCE)));
    }
}
