<?php

namespace Tests\Base\Service;

use Base\Service\BaseService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * BaseService is a lazy service (plan "omnibase agnostique", C9): what the
 * container hands out is a ghost of the class itself (PHP 8.4's lazy objects),
 * built - its 23 services with it - the first time one of its methods reads a
 * property. Injected in a class that never calls it, it builds nothing; and the
 * core's own classes on a page's path no longer call it (C9's third step: the
 * site's variables, the forms' extension, the error controller read the settings,
 * the parameters and the Environment contract themselves).
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

    /** @return iterable<string, array{string}> */
    public static function pages(): iterable
    {
        yield 'the sign-in page' => ['/login'];
        yield 'the home' => ['/'];
    }

    /** @dataProvider pages */
    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function testAPageOfTheHostLeavesItAGhost(string $path): void
    {
        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] ??= 'phpunit';

        $response = static::$kernel->handle(Request::create($path));
        $service = static::getContainer()->get('base.service');

        self::assertLessThan(500, $response->getStatusCode(), $path.' answers');
        self::assertTrue((new \ReflectionClass($service))->isUninitializedLazyObject($service), $path.' built BaseService');
    }

    /** BaseService asks the contracts what they answer (C9): the same answers, and the ghost stays one. */
    public function testWhatAContractAnswersBuildsNothing(): void
    {
        $container = static::getContainer();
        $service = $container->get('base.service');
        $environment = $container->get('Base\\Contract\\Environment');

        self::assertSame($environment->isDebug(), $service->isDebug());
        self::assertSame($environment->secret(), $service->getSalt());
        self::assertSame($container->getParameter('kernel.secret'), $service->getSecret());
        self::assertSame(\Base\Service\Model\DevelopmentEnvironment::is($environment), $service->isDevelopment());
        self::assertFalse($service->isGranted('ROLE_SUPERADMIN'));
        self::assertSame($container->get('parameter_bag'), BaseService::getParameterBag());
        self::assertSame($container->getParameter('kernel.environment'), BaseService::getEnvironment());
        self::assertTrue((new \ReflectionClass($service))->isUninitializedLazyObject($service));
    }
}
