<?php

namespace Tests\Base\Bundle;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Uploader;
use Omnibridge\Symfony\Omnibase\Routing\Generator\AdvancedUrlGenerator;
use Omnibridge\Symfony\Omnibase\Routing\Matcher\AdvancedUrlMatcher;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Base\Bundle\Fixtures\StaleContainerKernel;

/**
 * A container compiled before the attribute reader, the router and the enumerations were left to the compile step
 * (omnisense/omnibase 2d4e19f5, omnibridge/symfony 601d65e and 0fcc078) still boots with the new code: production
 * deploys new code over its var/cache/prod and runs cache:clear, which boots the old container first, and without
 * debug Symfony never rebuilds it. The boot crashed there ("Call to a member function getParameterBag() on null",
 * AbstractAttribute) and routed through Symfony's own matcher and generator: what the container lacks, the boot
 * makes as before.
 */
final class StaleContainerTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return StaleContainerKernel::class;
    }

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new StaleContainerKernel($options['environment'] ?? 'test', false);
    }

    public static function tearDownAfterClass(): void
    {
        if (class_exists('App\\Kernel')) {
            (new Filesystem())->remove((new StaleContainerKernel('test', false))->getCacheDir());
        }
        parent::tearDownAfterClass();
    }

    public function testAContainerCompiledBeforeBootsAsBefore(): void
    {
        if (!class_exists('App\\Kernel') || !\defined(AttributeReader::class.'::NAMES')) {
            self::markTestSkipped('Requires the host application kernel.');
        }
        (new Filesystem())->remove((new StaleContainerKernel('test', false))->getCacheDir());
        AttributeReader::setInstance(null);

        $kernel = self::bootKernel();
        $container = static::getContainer();

        // The container is the old one's shape.
        foreach (StaleContainerKernel::PARAMETERS as $name) {
            self::assertFalse($container->hasParameter($name), $name.' taken out');
        }
        self::assertFalse($container->get(\Base\Discovery::registry('contracts')['service'])->has(AttributeReader::class), 'no reader in the contracts\' locator');

        // The reader made by the boot, as before: the attributes reach the parameters.
        self::assertTrue($container->initialized(AttributeReader::class), 'made by the boot');
        self::assertSame($container->get(AttributeReader::class), AttributeReader::getInstance());
        self::assertNotEmpty(AttributeReader::getInstance()->getAttributeNames(), 'the folders read');
        self::assertSame($container->get(AttributeReader::class)->getParameterBag(), AbstractAttribute::getParameterBag());
        new Uploader();

        // omnibase's router made by the boot, as before: its constructor names its matcher and generator.
        self::assertTrue($container->initialized('advanced_router'), 'made by the boot');
        $router = $container->get('router');
        self::assertInstanceOf(AdvancedUrlGenerator::class, $router->getGenerator());
        self::assertInstanceOf(AdvancedUrlMatcher::class, $router->getMatcher());
        self::assertSame($container->get('advanced_router'), AdvancedUrlGenerator::$router);

        // A request through the kernel, a command.
        $response = $kernel->handle(Request::create($router->generate('security_login')));
        self::assertSame(200, $response->getStatusCode());

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        self::assertSame(0, $application->run(new ArrayInput(['command' => 'debug:router', 'name' => 'security_login']), $output), $output->fetch());
    }
}
