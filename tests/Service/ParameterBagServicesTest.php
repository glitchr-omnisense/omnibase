<?php

namespace Tests\Base\Service;

use Base\Contract\Runtime;
use Base\Service\BaseService;
use Base\Service\HotParameterBag;
use Base\Service\ParameterBagInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface as SymfonyParameterBagInterface;

/**
 * One parameter bag (plan "omnibase agnostique", C9): `parameter_bag`, the
 * autowiring of omnibase's ParameterBagInterface - which gave the plain
 * ParameterBag, without the settings stored in the database - and of
 * DependencyInjection's two interfaces, Runtime::parameters() and BaseTrait's
 * getParameterBag() are the same HotParameterBag.
 */
final class ParameterBagServicesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testTheWaysOfGettingTheBagGiveOneObject(): void
    {
        $container = static::getContainer();
        $bag = $container->get('parameter_bag');

        self::assertInstanceOf(HotParameterBag::class, $bag);
        self::assertSame($bag, $container->get(ParameterBagInterface::class));
        self::assertSame($bag, $container->get(SymfonyParameterBagInterface::class));
        self::assertSame($bag, $container->get(ContainerBagInterface::class));
        self::assertSame($bag, Runtime::parameters());
        self::assertSame($bag, BaseService::getParameterBag());
        self::assertSame($bag, (new \ReflectionProperty('Base\\Controller\\UX\\EditorController', 'parameterBag'))->getValue($container->get('Base\\Controller\\UX\\EditorController')), 'a service autowired on omnibase\'s interface');
    }

    public function testASettingStoredAsFalseWins(): void
    {
        $bag = static::getContainer()->get('parameter_bag');
        self::assertTrue($bag->isEnabled(), 'the harness turns the settings on');
        $configured = $bag->get('base.twig.use_bootstrap', null, false);

        $bag->add(['base.twig.use_bootstrap' => false]);

        self::assertTrue($configured);
        self::assertFalse($bag->get('base.twig.use_bootstrap'));
        self::assertFalse(Runtime::parameters()->get('base.twig.use_bootstrap'));
        self::assertSame($configured, $bag->get('base.twig.use_bootstrap', null, false));
    }
}
