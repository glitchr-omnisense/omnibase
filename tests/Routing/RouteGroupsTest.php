<?php

namespace Tests\Base\Routing;

use Base\Routing\AdvancedRouterInterface;
use Base\Service\Localizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * A route's group comes from an index of the compiled routes made once: it
 * was the scan of every compiled route at every call (575 on lesringostars,
 * 16 to 20 calls a page). The groups are the scan's, route by route, in the
 * same order. And an address made is no longer written to the router's cache
 * pool, which nothing read back.
 */
final class RouteGroupsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        static::getContainer()->get('request_stack')->push(Request::create('https://localhost/'));
    }

    /** The groups as the scan made them, before C10. */
    private static function scanned(array $routeNames, ?string $routeName): array
    {
        $routeName = explode('.', $routeName ?? '')[0];
        $groups = [];
        foreach ($routeNames as $_routeName) {
            if ($_routeName !== $routeName && !str_starts_with($_routeName, $routeName.'.')) {
                continue;
            }
            $withoutLocale = str_rstrip($_routeName, '.'.Localizer::getDefaultLocaleLang());
            if ($_routeName != $withoutLocale) {
                $groups[] = $withoutLocale;
            }
            $groups[] = $_routeName;
        }

        return array_unique($groups);
    }

    public function testEveryGroupIsTheScansOne(): void
    {
        $router = static::getContainer()->get(AdvancedRouterInterface::class);
        $generator = $router->getGenerator();
        if (!method_exists($generator, 'groups')) {
            self::markTestSkipped('The advanced generator is off in this application.');
        }
        $names = array_keys($generator->getCompiledRoutes());
        self::assertNotEmpty($names);

        foreach (array_merge($names, [null, '', 'no_such_route', 'no_such_route.fr']) as $name) {
            self::assertSame(self::scanned($names, $name), $generator->groups($name), (string) $name);
        }
    }

    public function testAnAddressIsNotWrittenToTheCachePool(): void
    {
        $router = static::getContainer()->get(AdvancedRouterInterface::class);
        if (!$router->useAdvancedFeatures()) {
            self::markTestSkipped('The advanced router is off in this application.');
        }
        $name = current(array_filter(['security_login', 'security_register', 'demo_index', 'admin'], fn ($name) => null !== $router->getRouteCollection()->get($name)));
        self::assertNotFalse($name);

        $router->getCache()->clear();
        $router->generate($name);

        self::assertFalse($router->getCache()->getItem($router->getCacheName().'.routes')->isHit());
        self::assertSame($router->generate($name), $router->generate($name));
    }
}
