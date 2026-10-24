<?php

namespace Tests\Base\Contract;

use Base\Bridge\Symfony\RouterRoutes;
use Base\Contract\Link;
use Base\Contract\Runtime;
use Base\Entity\Layout\Semantic;
use Base\Entity\Layout\Widget\Route;
use Omnibridge\Exception\MissingContractException;
use Base\Service\BaseService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What the routes are beyond their addresses, moved off BaseTrait's router and
 * IconProvider onto the Routes contract (plan "omnibase agnostique", C4b),
 * under Symfony: Semantic's and Widget\Route's declared path and route icons
 * are the router's and the IconProvider's of before (their old code copied),
 * Widget\Route's links the router's for the four reference types (through
 * Link::to(), as Semantic's since C3e), and getRoute() - which handed the
 * router's Route object to a string return type, a TypeError for any route
 * found - names the route the address leads to.
 */
final class RoutesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    /** @return list<array{string, array<string, mixed>}> routes of the core, one with icons */
    private static function routes(): array
    {
        $router = BaseService::getRouter();
        $routes = [];
        foreach ([['security_login', []], ['user_profile', []], ['short_redirect', ['slug' => 'x7Kq2']], ['user_settings', []]] as [$name, $parameters]) {
            if (null !== $router->getRouteCollection()->get($name)) {
                $routes[] = [$name, $parameters];
            }
        }

        // A route whose controller has icons (#[Iconize]), and that needs no parameter.
        foreach (array_keys(BaseService::getIconProvider()->getRouteIcons() ?? []) as $name) {
            $route = $router->getRouteCollection()->get($name);
            if ($route && !$route->compile()->getPathVariables()) {
                $routes[] = [$name, []];
                break;
            }
        }

        return $routes;
    }

    public function testTheRegistryHoldsTheRoutersRoutes(): void
    {
        self::assertInstanceOf(RouterRoutes::class, Runtime::routes());
    }

    public function testDeclaredPathsAndIconsAreTheRoutersOfBefore(): void
    {
        $router = BaseService::getRouter();
        $icons = BaseService::getIconProvider();
        $withIcons = 0;
        foreach (self::routes() as [$name, $parameters]) {
            foreach ([Semantic::class, Route::class] as $class) {
                $entity = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
                $entity->setRouteName($name);
                $entity->setRouteParameters($parameters);

                self::assertSame($router->getRouteCollection()->get($name)?->getPath(), $entity->getPath(), $class.', '.$name);
                self::assertNotNull($entity->getPath());
                self::assertSame($icons->getRouteIcons($name), $entity->getRouteIcons(), $class.', '.$name.', icons');
                $withIcons += (int) (null !== $entity->getRouteIcons());

                // The route its address leads to; the old getRoute() threw on any route found.
                $url = $router->generate($name, $parameters);
                self::assertSame($router->getRouteName($url), $entity->getRoute(), $class.', '.$name.', route');
                self::assertNotNull($entity->getRoute());
                try {
                    (fn (): ?string => $router->getRoute($url))();
                    self::fail('the router answers a Route object, not a string');
                } catch (\TypeError) {
                }
            }
        }
        self::assertGreaterThan(0, $withIcons, 'a route with icons was compared');

        $semantic = (new \ReflectionClass(Semantic::class))->newInstanceWithoutConstructor();
        self::assertNull($semantic->getPath(), 'no route');
        self::assertNull($semantic->getRouteIcons());
        $semantic->setRouteName('no_such_route_c4b');
        self::assertNull($semantic->getPath());
        self::assertNull($semantic->getRoute(), 'an address the router cannot make');
    }

    public function testARouteWidgetsLinksAreTheRoutersAddresses(): void
    {
        $router = BaseService::getRouter();
        foreach (self::routes() as [$name, $parameters]) {
            $widget = new Route('A route', $name, $parameters);
            foreach ([Link::ABSOLUTE_URL, Link::ABSOLUTE_PATH, Link::RELATIVE_PATH, Link::NETWORK_PATH] as $type) {
                self::assertSame($router->generate($name, $parameters, $type), $widget->generate($type), $name.', type '.$type);
                self::assertSame($router->generate($name, array_merge($parameters, ['utm' => 'menu']), $type), (new Route('A route', $name, $parameters))->__toLink(['utm' => 'menu'], $type), $name.', __toLink, type '.$type);
            }
            self::assertSame($router->generate($name, $parameters), $widget->getUrl());
            self::assertSame($router->generate($name, $parameters), $widget->__toLink());
        }

        self::assertNull((new Route('Nowhere', 'no_such_route_c4b'))->getUrl(), 'a route the router cannot make: null, as before');

        $widget = new Route('A route', 'security_login');
        $container = Runtime::getContainer();
        Runtime::setContainer(null);
        try {
            $this->expectException(MissingContractException::class);
            $widget->getUrl();
        } finally {
            Runtime::setContainer($container);
        }
    }
}
