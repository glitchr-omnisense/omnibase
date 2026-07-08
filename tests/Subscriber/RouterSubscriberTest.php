<?php

namespace Tests\Base\Subscriber;

use Base\Routing\AdvancedRouterInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Base\Subscriber\RouterSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * `debug:router`/`router:match` are Symfony's own commands: they read
 * Route::getHost() / RouteCollection directly and never go through the
 * `matcher_class`/`generator_class` options AdvancedRouter wires onto the
 * `router` service, so host-templated routes (Base\Attributes\Attribute\Route)
 * always showed/matched their literal, unsubstituted sentinel tokens under
 * those two commands. RouterSubscriber::onCommand() resolves the tokens
 * directly on the shared RouteCollection right before either command runs,
 * using the exact same fallback getters real request handling uses.
 */
class RouterSubscriberTest extends TestCase
{
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    private function makeEvent(string $commandName): ConsoleEvent
    {
        $command = new Command($commandName);

        return new ConsoleEvent($command, new ArrayInput([]), new NullOutput());
    }

    private function makeRequestEvent(bool $isMainRequest = true): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestType = $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, Request::create('/'), $requestType);
    }

    private function makeSubscriber(
        AdvancedRouterInterface $router,
        ?AuthorizationCheckerInterface $authorizationChecker = null,
        ?ParameterBagInterface $parameterBag = null,
        ?SettingBagInterface $settingBag = null
    ): RouterSubscriber {
        return new RouterSubscriber(
            $authorizationChecker ?? $this->createMock(AuthorizationCheckerInterface::class),
            $router,
            $parameterBag ?? $this->createMock(ParameterBagInterface::class),
            $settingBag ?? $this->createMock(SettingBagInterface::class)
        );
    }

    public function testResolvesHostSentinelsForDebugRouterCommand(): void
    {
        $collection = new RouteCollection();
        $collection->add('app_calendar', new Route('/calendar', [], [], [], 'https://\{_machine\}.\{_subdomain\}.\{_domain\}:\{_port\}'));

        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);
        $router->method('getMachine')->willReturn('beta');
        $router->method('getSubdomain')->willReturn('m');
        $router->method('getDomain')->willReturn('example.org');
        $router->method('getPort')->willReturn(null);

        $this->makeSubscriber($router)->onCommand($this->makeEvent('debug:router'));

        $this->assertSame('https://beta.m.example.org', $collection->get('app_calendar')->getHost());
    }

    public function testResolvesHostSentinelsForRouterMatchCommand(): void
    {
        $collection = new RouteCollection();
        $collection->add('app_calendar', new Route('/calendar', [], [], [], 'https://\{_machine\}.\{_subdomain\}.\{_domain\}:\{_port\}'));

        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);
        $router->method('getMachine')->willReturn(null);
        $router->method('getSubdomain')->willReturn('m');
        $router->method('getDomain')->willReturn('example.org');
        $router->method('getPort')->willReturn(8443);

        $this->makeSubscriber($router)->onCommand($this->makeEvent('router:match'));

        // No machine: its segment (and trailing dot) is dropped entirely, not left as "".
        $this->assertSame('https://m.example.org:8443', $collection->get('app_calendar')->getHost());
    }

    public function testUnrelatedCommandsLeaveTheRouteCollectionUntouched(): void
    {
        $collection = new RouteCollection();
        $collection->add('app_calendar', new Route('/calendar', [], [], [], 'https://\{_machine\}.\{_subdomain\}.\{_domain\}:\{_port\}'));

        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->expects($this->never())->method('getRouteCollection');

        $this->makeSubscriber($router)->onCommand($this->makeEvent('cache:clear'));

        $this->assertSame('https://\{_machine\}.\{_subdomain\}.\{_domain\}:\{_port\}', $collection->get('app_calendar')->getHost());
    }

    public function testRoutesWithoutSentinelTokensAreLeftUntouched(): void
    {
        $collection = new RouteCollection();
        $collection->add('app_plain', new Route('/plain'));
        $collection->add('app_pinned', new Route('/pinned', [], [], [], 'fixed.example.com'));

        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);
        $router->method('getMachine')->willReturn('beta');
        $router->method('getSubdomain')->willReturn('m');
        $router->method('getDomain')->willReturn('example.org');
        $router->method('getPort')->willReturn(null);

        $this->makeSubscriber($router)->onCommand($this->makeEvent('debug:router'));

        $this->assertSame('', $collection->get('app_plain')->getHost());
        $this->assertSame('fixed.example.com', $collection->get('app_pinned')->getHost());
    }

    public function testSubRequestsAreIgnored(): void
    {
        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->expects($this->never())->method('getRoute');

        $this->makeSubscriber($router)->onKernelRequest($this->makeRequestEvent(isMainRequest: false));
    }

    public function testNoRouteFoundNeverRedirects(): void
    {
        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('getRoute')->willReturn(null);

        $event = $this->makeRequestEvent();
        $this->makeSubscriber($router)->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testMatchingUrlNeverRedirects(): void
    {
        $_SERVER['HTTP_HOST'] = 'm.example.org';
        $_SERVER['REQUEST_URI'] = '/calendar';
        unset($_SERVER['HTTPS'], $_SERVER['USE_HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('getRoute')->willReturn(new Route('/calendar'));
        $router->method('reducesOnFallback')->willReturn(false);

        // ip_access true short-circuits the ipRestriction branch before
        // isGranted() is ever consulted.
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('base.router.ip_access')->willReturn(true);

        $event = $this->makeRequestEvent();
        $this->makeSubscriber($router, null, $parameterBag)->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testIpRestrictionWithAnIpFallbackThrows(): void
    {
        $_SERVER['HTTP_HOST'] = 'm.example.org';
        $_SERVER['REQUEST_URI'] = '/admin';

        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('getRoute')->willReturn(new Route('/admin'));
        $router->method('getHostFallback')->willReturn('127.0.0.1');

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('base.router.ip_access')->willReturn(false);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/IP access is disallowed/');

        $this->makeSubscriber($router, $authorizationChecker, $parameterBag)
            ->onKernelRequest($this->makeRequestEvent());
    }

    public function testIpRestrictionRedirectsToTheFallbackHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'm.example.org';
        $_SERVER['REQUEST_URI'] = '/secret';

        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('getRoute')->willReturn(new Route('/secret'));
        $router->method('getHostFallback')->willReturn('trusted.example.com');
        $router->method('getScheme')->willReturn('https');
        $router->method('getPortFallback')->willReturn(null);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('base.router.ip_access')->willReturn(false);

        $event = $this->makeRequestEvent();
        $this->makeSubscriber($router, $authorizationChecker, $parameterBag)->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
        $this->assertStringContainsString('trusted.example.com', $event->getResponse()->getTargetUrl());
    }

    /**
     * Reproduces the "kernel sub-requests are unreliable here" host-reduction
     * redirect noted in the beta-env-diagnostics memory: when the current
     * host carries a machine prefix ("beta.") that the resolved machine
     * doesn't call for, the reduction branch redirects to the bare
     * subdomain+domain host.
     */
    public function testReductionRedirectsAwayFromAnUnwantedMachinePrefix(): void
    {
        $_SERVER['HTTP_HOST'] = 'beta.m.example.org';
        $_SERVER['REQUEST_URI'] = '/calendar';

        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('getRoute')->willReturn(new Route('/calendar'));
        $router->method('reducesOnFallback')->willReturn(true);
        $router->method('getMachine')->willReturn(null);
        $router->method('getSubdomain')->willReturn('m');
        $router->method('getDomain')->willReturn('example.org');
        $router->method('getPort')->willReturn(null);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(false);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('base.router.ip_access')->willReturn(true);

        $event = $this->makeRequestEvent();
        $this->makeSubscriber($router, $authorizationChecker, $parameterBag)->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('m.example.org', $response->getTargetUrl());
        $this->assertStringNotContainsString('beta.m.example.org', $response->getTargetUrl());
    }
}
