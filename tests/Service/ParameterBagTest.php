<?php

namespace Tests\Base\Service;

use Base\Service\HotParameterBag;
use Base\Service\ParameterBag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * The parameter bag wraps Symfony's ContainerBag instead of extending it
 * (plan "omnibase agnostique", P3): it loads on DependencyInjection as
 * published - FrozenParameterBag's set() and add() return never, this bag's
 * set() and HotParameterBag's add() return void - and behaves as before.
 */
final class ParameterBagTest extends TestCase
{
    private static function container(): Container
    {
        return new Container(new FrozenParameterBag([
            'base.site.title' => 'Les Ringo Stars',
            'base.site.locale' => 'fr',
            'base.parameter_bag.use_hot_bag' => true,
            'kernel.project_dir' => '/srv/app',
        ]));
    }

    public function testWhatTheCodeTypesIt(): void
    {
        $bag = new ParameterBag(self::container());

        self::assertInstanceOf(ParameterBagInterface::class, $bag);
        self::assertInstanceOf(ContainerBagInterface::class, $bag);
        self::assertInstanceOf(\Base\Service\ParameterBagInterface::class, $bag);
        self::assertNotInstanceOf(FrozenParameterBag::class, $bag);
        self::assertSame('void', (string) (new \ReflectionMethod(ParameterBag::class, 'set'))->getReturnType());
    }

    public function testItReadsTheContainersParametersByPath(): void
    {
        $bag = new ParameterBag(self::container());

        self::assertSame('Les Ringo Stars', $bag->get('base.site.title'));
        self::assertSame(['title' => 'Les Ringo Stars', 'locale' => 'fr'], $bag->get('base.site'));
        self::assertTrue($bag->has('base.site.locale'));
        self::assertFalse($bag->has('base.site.nothing'));
        self::assertSame('/srv/app', $bag->all()['kernel.project_dir']);
        self::assertSame('/srv/app/var', $bag->resolveValue('%kernel.project_dir%/var'));
        self::assertSame('%%x', $bag->escapeValue('%x'));
        self::assertSame('%x', $bag->unescapeValue('%%x'));
    }

    public function testItForbidsWhatTheFrozenBagForbade(): void
    {
        $bag = new ParameterBag(self::container());

        foreach (['clear' => [], 'add' => [['a' => 1]], 'remove' => ['a']] as $method => $arguments) {
            try {
                $bag->{$method}(...$arguments);
                self::fail($method.'() should throw');
            } catch (LogicException $e) {
                self::assertSame(sprintf('Impossible to call %s() on a frozen ParameterBag.', $method), $e->getMessage());
            }
        }
    }

    public function testTheHotBagKeepsWhatItIsGiven(): void
    {
        $bag = new HotParameterBag(self::container());
        $bag->add(['base.hot.value' => 42]);

        self::assertSame(42, $bag->all()['base.hot.value']);
        self::assertSame('Les Ringo Stars', $bag->get('base.site.title'));
    }

    /** HotParameterBag is HotParameters (the Foundation's overlay) as DependencyInjection's two interfaces (C9). */
    public function testTheHotBagIsTheOverlayAsTheFrameworksBag(): void
    {
        $bag = new HotParameterBag(self::container());

        self::assertInstanceOf(\Base\Service\HotParameters::class, $bag);
        self::assertInstanceOf(\Base\Service\HotParameterBagInterface::class, $bag);
        self::assertInstanceOf(ParameterBagInterface::class, $bag);
        self::assertInstanceOf(ContainerBagInterface::class, $bag);
        self::assertSame('/srv/app/var', $bag->resolveValue('%kernel.project_dir%/var'));
        self::assertSame('%%x', $bag->escapeValue('%x'));
        foreach (['clear' => [], 'remove' => ['a']] as $method => $arguments) {
            try {
                $bag->{$method}(...$arguments);
                self::fail($method.'() should throw');
            } catch (LogicException $e) {
                self::assertSame(sprintf('Impossible to call %s() on a frozen ParameterBag.', $method), $e->getMessage());
            }
        }

        $bag->add(['base.site.locale' => false]);
        self::assertFalse($bag->get('base.site.locale'), 'a false setting over the configuration');
        self::assertTrue($bag->has('base.site.locale'));
    }

    /** has() is whether get() finds a value: a parameter set to false is there (C9; it was its truth). */
    public function testHasIsThePresenceOfAValue(): void
    {
        $container = new Container(new FrozenParameterBag(['base.flag' => false, 'base.zero' => 0, 'base.none' => null]));
        $bag = new ParameterBag($container);

        self::assertTrue($bag->has('base.flag'));
        self::assertTrue($bag->has('base.zero'));
        self::assertFalse($bag->has('base.none'));
        self::assertFalse($bag->has('base.nothing'));
    }
}
