<?php

namespace Tests\Base\Service;

use Base\Service\BaseService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * BaseService is a lazy service (plan "omnibase agnostique", C9): what the
 * container hands out is a ghost of the class itself (PHP 8.4's lazy objects),
 * built - its 23 services with it - the first time one of its methods reads a
 * property. Injected in a class that never calls it, it builds nothing.
 */
final class BaseServiceLazyTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testTheContainerHandsOutAGhostOfTheClassItself(): void
    {
        $service = static::getContainer()->get('base.service');

        self::assertSame(BaseService::class, $service::class, 'a ghost, not a proxy subclass');
        self::assertSame($service, static::getContainer()->get(BaseService::class));
        self::assertTrue((new \ReflectionClass($service))->isUninitializedLazyObject($service));
    }

    public function testItIsBuiltWhenAMethodReadsItsServices(): void
    {
        $service = static::getContainer()->get('base.service');

        self::assertIsFloat($service->getExecutionTime());
        self::assertFalse((new \ReflectionClass($service))->isUninitializedLazyObject($service));
        self::assertSame($service, BaseService::getService(), 'the instance it records is the object handed out');
    }
}
