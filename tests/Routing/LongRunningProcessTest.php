<?php

namespace Tests\Base\Routing;

use Base\Routing\AdvancedRouter;
use Base\Twig\Renderer\Adapter\HtmlTagRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;

/**
 * One process answering several requests - FrankenPHP's worker, a static
 * export: nothing one page left in omnibase's services reaches the next.
 *
 * - AdvancedRouter kept a localized route (thread_search.fr) under its
 *   canonical name: the first page's language was in every later page's links.
 *   It is kept under its locale's name, and forgotten at kernel.reset.
 * - HtmlTagRenderer kept what a page's templates added (timeline.css) for
 *   every page after it: forgotten at kernel.reset.
 * And two different pages asked of one kernel, in a row: the second shows
 * nothing of the first's.
 */
final class LongRunningProcessTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
    }

    public function testALocalizedRouteIsTheLocalesOwn(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $router = $container->get('advanced_router');
        self::assertInstanceOf(AdvancedRouter::class, $router);
        $translator = $container->get('translator');

        $translator->setLocale('fr');
        $french = $router->getRoute('thread_search')?->getPath();
        $translator->setLocale('en');
        $english = $router->getRoute('thread_search')?->getPath();

        self::assertSame('/rechercher', $french);
        self::assertSame('/search', $english, 'the French route asked first is not the English page\'s');
    }

    public function testTheServicesForgetTheRequestAtKernelReset(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $html = $container->get('twig.html_renderer');
        self::assertInstanceOf(HtmlTagRenderer::class, $html);
        self::assertInstanceOf(ResetInterface::class, $html);
        self::assertInstanceOf(ResetInterface::class, $container->get('advanced_router'));

        $html->addHtmlContent('stylesheets', '<link rel="stylesheet" href="/timeline.css">');
        self::assertStringContainsString('timeline.css', $html->getHtmlContent('stylesheets'));

        $container->get('services_resetter')->reset();
        self::assertSame('', $html->getHtmlContent('stylesheets'), 'what a page added is not the next page\'s');
    }

    public function testTwoPagesInARowShareNothing(): void
    {
        $kernel = self::bootKernel();
        $first = $kernel->handle(Request::create('/rechercher'));
        if ($kernel instanceof \Symfony\Component\HttpKernel\TerminableInterface) {
            $kernel->terminate(Request::create('/rechercher'), $first);
        }
        self::assertSame(200, $first->getStatusCode());
        // What a worker does between two requests (Symfony's runtime resets the kernel's services).
        static::getContainer()->get('services_resetter')->reset();
        $second = $kernel->handle(Request::create('/search'));
        self::assertSame(200, $second->getStatusCode());

        self::ensureKernelShutdown();
        $alone = self::bootKernel()->handle(Request::create('/search'));

        // The addresses the page links and loads, after another page or alone: the same.
        self::assertSame(self::addresses((string) $alone->getContent()), self::addresses((string) $second->getContent()));
    }

    /** @return list<string> every href and src of a page, sorted */
    private static function addresses(string $html): array
    {
        preg_match_all('/\b(?:href|src)="([^"]*)"/', $html, $matches);
        $addresses = array_values(array_unique($matches[1]));
        sort($addresses);

        return $addresses;
    }
}
