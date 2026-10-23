<?php

namespace Tests\Base\Cache;

use Base\Twig\Renderer\Adapter\WebpackTagRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A renderer booted from a warm cache knows the breakpoints and the
 * alternatives as one booted cold does (master plan §5, invariant 3). They
 * were set by discoverEntrypoints() alone, which only the cache warmer runs:
 * every later process had none, and printed no stylesheet of a breakpoint's
 * (layout1-async-mobile.css, media="all and (max-width: 470px)"...) - a page
 * answered otherwise from a warm cache than from a cold one (lesringostars'
 * CacheLifecycleTest showed it).
 */
class WebpackWarmCacheTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        self::bootKernel();
        if (!static::getContainer()->getParameter('base.twig.use_custom')) {
            self::markTestSkipped('The host does not render its tags through omnibase (base.twig.use_custom).');
        }
    }

    public function testAWarmBootKnowsTheBreakpointsAndTheAlternatives(): void
    {
        $configured = static::getContainer()->getParameter('base.twig.breakpoints') ?? [];

        // What a process booting from a warm cache starts from: no discovery ran in it.
        $renderer = clone static::getContainer()->get(WebpackTagRenderer::class);
        (new \ReflectionProperty(WebpackTagRenderer::class, 'breakpoints'))->setValue($renderer, []);
        (new \ReflectionProperty(WebpackTagRenderer::class, 'alternatives'))->setValue($renderer, []);
        $renderer->warmUp(static::getContainer()->getParameter('kernel.cache_dir'));

        $expected = [];
        foreach ($configured as $breakpoint) {
            $expected[$breakpoint['media'] ?? 'all'][] = $breakpoint['name'];
        }
        self::assertSame($expected, $renderer->getBreakpoints());
        self::assertSame(['async', 'defer'], $renderer->getAlternatives());

        // Again, as a second warm-up in the process does: nothing doubled.
        $renderer->warmUp(static::getContainer()->getParameter('kernel.cache_dir'));
        self::assertSame($expected, $renderer->getBreakpoints());
        self::assertSame(['async', 'defer'], $renderer->getAlternatives());
    }
}
