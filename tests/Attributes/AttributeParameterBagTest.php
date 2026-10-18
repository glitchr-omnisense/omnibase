<?php

namespace Tests\Base\Attributes;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Service\ParameterBag;
use Base\Service\ParameterBagInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface as SymfonyParameterBagInterface;

/**
 * The attributes read the parameters through omnibase's contract,
 * Base\Service\ParameterBagInterface, no longer DependencyInjection's (plan
 * "omnibase agnostique", step C3c). The contract extends nothing of the
 * framework; Base\Service\ParameterBag is its Symfony adapter, and still
 * DependencyInjection's bag for the code typing it; the reader is given
 * `parameter_bag` (HotParameterBag over it, C9).
 */
final class AttributeParameterBagTest extends KernelTestCase
{
    public function testTheContractIsTheCoresAlone(): void
    {
        self::assertSame([], class_implements(ParameterBagInterface::class));
        foreach (['get', 'set', 'has', 'all'] as $method) {
            self::assertTrue(method_exists(ParameterBagInterface::class, $method), $method);
        }

        self::assertTrue(is_subclass_of(ParameterBag::class, ParameterBagInterface::class));
        self::assertTrue(is_subclass_of(ParameterBag::class, SymfonyParameterBagInterface::class));

        $type = (new \ReflectionMethod(AbstractAttribute::class, 'getParameterBag'))->getReturnType();
        self::assertSame(ParameterBagInterface::class, $type instanceof \ReflectionNamedType ? $type->getName() : null);
        $type = (new \ReflectionMethod(AttributeReader::class, 'getParameterBag'))->getReturnType();
        self::assertSame(ParameterBagInterface::class, $type instanceof \ReflectionNamedType ? $type->getName() : null);
    }

    public function testTheReaderHasTheContainersParameters(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        $container = static::getContainer();
        $container->get(AttributeReader::class);

        $bag = AbstractAttribute::getParameterBag();
        // `parameter_bag` itself since C9 (it was the plain ParameterBag, without the settings stored in the database)
        self::assertSame($container->get('parameter_bag'), $bag);
        self::assertSame($container->getParameter('base.uploader.storage'), $bag->get('base.uploader.storage'));
        self::assertSame($container->getParameter('kernel.environment'), $bag->all()['kernel.environment'] ?? null);
    }
}
