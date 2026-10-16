<?php

namespace Tests\Base\Subscriber;

use Base\Routing\AdvancedRouterInterface;
use Base\Service\ParameterBag;
use Base\Subscriber\TwigSubscriber;
use Base\Twig\Renderer\Adapter\HtmlTagRenderer;
use Base\Twig\Renderer\Adapter\WebpackTagRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Every HTML page got the core's additions at the response (the breakpoints'
 * styles, the HTML queued while rendering, the webpack entries not printed),
 * a page rendered whole by another framework too included: lesringostars'
 * article screen came out 60 KB longer under Symfony than under Laravel. A
 * route opts out with the default `_omnibase_inject: false`; any other page
 * gets them as before.
 */
final class TwigSubscriberInjectTest extends TestCase
{
    public function testARouteThatOptsOutIsLeftAsItWasRendered(): void
    {
        $request = new Request();
        $request->attributes->set(TwigSubscriber::INJECT, false);

        self::assertFalse($this->subscriber(expectedFallbacks: 0)->onKernelResponse($this->event($request)));
    }

    public function testAnyOtherPageGetsTheAdditionsAsBefore(): void
    {
        self::assertTrue($this->subscriber(expectedFallbacks: 1)->onKernelResponse($this->event(new Request())));
    }

    private function subscriber(int $expectedFallbacks): TwigSubscriber
    {
        $html = $this->createMock(HtmlTagRenderer::class);
        $html->expects(self::exactly($expectedFallbacks))->method('renderFallback')->willReturnArgument(0);
        $webpack = $this->createMock(WebpackTagRenderer::class);
        $webpack->expects(self::exactly($expectedFallbacks))->method('renderFallback')->willReturnArgument(0);
        $parameters = $this->createMock(ParameterBag::class);
        $parameters->method('get')->willReturnCallback(static fn (string $name) => 'base.twig.autoappend' === $name ? true : null);
        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('isProfiler')->willReturn(false);

        return new TwigSubscriber($html, $webpack, $this->createMock(AuthorizationCheckerInterface::class), $parameters, $router);
    }

    private function event(Request $request): ResponseEvent
    {
        return new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response('<html><head></head><body></body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']));
    }
}
