<?php

namespace Tests\Base\Routing;

use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\AdvancedRouterPass;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouter;
use Omnibridge\Symfony\Omnibase\Routing\Generator\AdvancedUrlGenerator;
use Omnibridge\Symfony\Omnibase\Routing\Matcher\AdvancedUrlMatcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * omnibase's router (advanced_router) is no longer made by each kernel's boot: Symfony's router names omnibase's
 * matcher and generator from the container's compilation (omnibridge/symfony's AdvancedRouterPass), and they make the
 * router from the booted kernel's container when they are first made. Its constructor set those options, so the boot
 * made it - with Symfony's router, the localizer, the firewall map and the cache - for a command that routes nothing.
 */
final class RouterMadeOnDemandTest extends KernelTestCase
{
    public function testThePassNamesTheMatcherAndTheGeneratorOnSymfonysRouter(): void
    {
        if (!class_exists(AdvancedRouterPass::class)) {
            self::markTestSkipped('Requires omnibridge/symfony\'s AdvancedRouterPass.');
        }

        $container = new ContainerBuilder();
        $container->register('router.default')->setArguments([null, 'kernel::loadRoutes', ['cache_dir' => '%router.cache_dir%', 'matcher_class' => 'Symfony\Bundle\FrameworkBundle\Routing\RedirectableCompiledUrlMatcher']]);
        (new AdvancedRouterPass())->process($container);
        self::assertSame('Symfony\Bundle\FrameworkBundle\Routing\RedirectableCompiledUrlMatcher', $container->getDefinition('router.default')->getArgument(2)['matcher_class'], 'no omnibase router: Symfony\'s');
        // Where it named them, the pass says so: a container compiled before it, without the parameter, has the boot
        // make the router as before (StaleContainerTest).
        $marked = \defined(AdvancedRouterPass::class.'::PARAMETER');
        if ($marked) {
            self::assertFalse($container->hasParameter(AdvancedRouterPass::PARAMETER));
        }

        $container->register('Base\Routing\AdvancedRouter');
        $container->setAlias('advanced_router', 'Base\Routing\AdvancedRouter');
        (new AdvancedRouterPass())->process($container);
        $options = $container->getDefinition('router.default')->getArgument(2);
        self::assertSame(AdvancedUrlMatcher::class, $options['matcher_class']);
        self::assertSame(AdvancedUrlGenerator::class, $options['generator_class']);
        self::assertSame('%router.cache_dir%', $options['cache_dir'], 'the other options kept');
        if ($marked) {
            self::assertTrue($container->getParameter(AdvancedRouterPass::PARAMETER));
        }
    }

    public function testTheRouterIsMadeWhenRoutingFirstNeedsIt(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists(AdvancedRouterPass::class)) {
            self::markTestSkipped('Requires the host application kernel and omnibridge/symfony\'s AdvancedRouterPass.');
        }
        self::bootKernel();
        $container = static::getContainer();

        self::assertFalse($container->initialized('advanced_router'), 'not made by the boot');
        self::assertNull(AdvancedUrlMatcher::$router, 'a previous kernel\'s router forgotten');
        self::assertNull(AdvancedUrlGenerator::$router);

        $router = $container->get('router');
        self::assertSame(AdvancedUrlMatcher::class, $router->getOption('matcher_class'));
        self::assertSame(AdvancedUrlGenerator::class, $router->getOption('generator_class'));
        self::assertFalse($container->initialized('advanced_router'), 'Symfony\'s router made, omnibase\'s not yet');

        self::assertInstanceOf(AdvancedUrlGenerator::class, $router->getGenerator());
        self::assertTrue($container->initialized('advanced_router'), 'made by the generator');
        $advanced = $container->get('advanced_router');
        self::assertSame($advanced, AdvancedUrlGenerator::$router);
        self::assertSame($advanced, AdvancedUrlMatcher::$router);
        self::assertSame($advanced, AdvancedRouter::current());
        self::assertInstanceOf(AdvancedUrlMatcher::class, $router->getMatcher());

        // A second kernel: its own router, made when it first routes; the first one's stays its generator's.
        self::ensureKernelShutdown();
        self::assertSame($advanced, AdvancedUrlGenerator::$router, 'kept past its kernel');
        self::assertNull(AdvancedRouter::current(), 'none made from a kernel shut down');
        self::bootKernel();
        $second = static::getContainer();
        self::assertFalse($second->initialized('advanced_router'));
        self::assertNull(AdvancedUrlGenerator::$router);
        self::assertInstanceOf(AdvancedUrlMatcher::class, $second->get('router')->getMatcher());
        self::assertSame($second->get('advanced_router'), AdvancedUrlMatcher::$router);
        self::assertNotSame($advanced, AdvancedUrlMatcher::$router);
    }

    public function testAGeneratorMadeWithoutARouterIsSymfonys(): void
    {
        if (!class_exists(AdvancedRouterPass::class)) {
            self::markTestSkipped('Requires omnibridge/symfony\'s AdvancedRouterPass.');
        }
        // No container handed (a kernel booted by another test and never shut down would have left its own).
        $container = new \ReflectionProperty(AdvancedRouter::class, 'container');
        $handed = $container->getValue();
        $container->setValue(null, null);
        $previous = AdvancedUrlGenerator::$router;
        AdvancedUrlGenerator::$router = null;
        try {
            $routes = new \Symfony\Component\Routing\RouteCollection();
            $routes->add('note', new \Symfony\Component\Routing\Route('/notes/{slug}'));
            $compiled = (new \Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper($routes))->getCompiledRoutes();
            $generator = new AdvancedUrlGenerator($compiled, new \Symfony\Component\Routing\RequestContext());

            self::assertNull(AdvancedRouter::current());
            self::assertSame('/notes/first', $generator->generate('note', ['slug' => 'first']));
        } finally {
            AdvancedUrlGenerator::$router = $previous;
            $container->setValue(null, $handed);
        }
    }
}
