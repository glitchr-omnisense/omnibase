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
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

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
    private function makeEvent(string $commandName): ConsoleEvent
    {
        $command = new Command($commandName);

        return new ConsoleEvent($command, new ArrayInput([]), new NullOutput());
    }

    private function makeSubscriber(AdvancedRouterInterface $router): RouterSubscriber
    {
        return new RouterSubscriber(
            $this->createMock(AuthorizationChecker::class),
            $router,
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(SettingBagInterface::class)
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
}
