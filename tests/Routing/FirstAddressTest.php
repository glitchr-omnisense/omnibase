<?php

namespace Tests\Base\Routing;

use Base\Routing\AdvancedRouterInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A route's address is the same the first time it is made and every time
 * after: the advanced generator handed the first one, before the route was in
 * its cache, through sanitize_url() - a slash before the query
 * (/chroniques/?page=2), the root's slash dropped (http://localhost) - and the
 * next ones as the router wrote them (/chroniques?page=2, http://localhost/),
 * so a page's links depended on the order Twig or a controller made them in
 * (lesringostars' screens added their query by hand for it; omnibase/blog's
 * snapshots met it at step C5).
 */
final class FirstAddressTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        static::getContainer()->get('request_stack')->push(Request::create('https://localhost/'));
    }

    public function testTheFirstAddressIsTheNextOnes(): void
    {
        $router = static::getContainer()->get(AdvancedRouterInterface::class);
        if (!$router->useAdvancedFeatures()) {
            self::markTestSkipped('The advanced router is off in this application.');
        }
        $routes = array_values(array_filter(['security_login', 'security_register', 'demo_index', 'admin'], fn ($name) => null !== $router->getRouteCollection()->get($name)));
        self::assertNotEmpty($routes);

        foreach ($routes as $name) {
            foreach ([['page' => 2, 'q' => 'été'], []] as $parameters) {
                foreach ([UrlGeneratorInterface::ABSOLUTE_PATH, UrlGeneratorInterface::ABSOLUTE_URL] as $type) {
                    $router->getCache()?->clear();
                    (new \ReflectionProperty($generator = $router->getGenerator(), 'cachedRoutes'))->setValue($generator, []);
                    $first = $router->generate($name, $parameters, $type);
                    $second = $router->generate($name, $parameters, $type);
                    self::assertSame($second, $first, $name.' '.json_encode($parameters).' '.$type);
                    if ($parameters && '/' !== $router->getRouteCollection()->get($name)->getPath()) {
                        self::assertStringNotContainsString('/?', $first, 'no slash put before the query');
                    }
                }
            }
        }
    }
}
