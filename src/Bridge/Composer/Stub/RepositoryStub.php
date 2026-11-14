<?php

namespace Base\Bridge\Composer\Stub;

use Omnibridge\Composer\Stub\StubContext;

/**
 * The stubs of App\Repository\ (see AbstractBaseStub). The core's repositories are
 * omnibridge/doctrine's since the plan "omnibase agnostic"'s step F3
 * (Omnibridge\Doctrine\Omnibase\Repository\X): their stubs extend them by that name, which
 * the application's App\Repository\X stands for at run time (5.x named them Base\Repository\X,
 * a former name the core aliased until 6.0).
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
}
