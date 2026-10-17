<?php

namespace Tests\Base\Contract;

use Base\Contract\Link;
use Base\Contract\Runtime;
use Base\Entity\Layout\Semantic;
use Base\Entity\Layout\ShortLink;
use Base\Exception\MissingContractException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The entities' getters C3e moved off BaseTrait's services onto the contracts
 * (plan "omnibase agnostique"), under Symfony: each answers what the service
 * it used answers - the router, the authorization checker, the Localizer -
 * compared here with the old path, one by one, as ContractAdaptersTest
 * compares the adapters. tests/BareTest's
 * testTheEntitiesAnswerThroughTheContractsWithoutTheFramework runs the same
 * getters where there is no framework.
 */
final class EntityGettersTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testLinksReferenceTypesAreTheRoutersConstants(): void
    {
        self::assertSame(UrlGeneratorInterface::ABSOLUTE_URL, Link::ABSOLUTE_URL);
        self::assertSame(UrlGeneratorInterface::ABSOLUTE_PATH, Link::ABSOLUTE_PATH);
        self::assertSame(UrlGeneratorInterface::RELATIVE_PATH, Link::RELATIVE_PATH);
        self::assertSame(UrlGeneratorInterface::NETWORK_PATH, Link::NETWORK_PATH);
    }

    /**
     * Semantic::__toLink() and getUrl(), ShortLink::__toLink() and Link::to() give the router's
     * strings for the four reference types (BaseTrait's getRouter() is the same advanced_router).
     */
    public function testLinksAreTheRoutersAddresses(): void
    {
        $router = static::getContainer()->get('advanced_router');
        $types = [Link::ABSOLUTE_URL, Link::ABSOLUTE_PATH, Link::RELATIVE_PATH, Link::NETWORK_PATH];
        $checked = 0;

        foreach ([['security_login', []], ['user_profile', []], ['short_redirect', ['slug' => 'x7Kq2']]] as [$route, $parameters]) {
            if (null === $router->getRouteCollection()->get($route)) {
                continue;
            }

            $semantic = (new \ReflectionClass(Semantic::class))->newInstanceWithoutConstructor();
            $semantic->setRouteName($route);
            $semantic->setRouteParameters($parameters);
            foreach ($types as $type) {
                self::assertSame($router->generate($route, $parameters, $type), $semantic->__toLink($type), $route.', type '.$type);
                self::assertSame($router->generate($route, $parameters, $type), Link::to($route, $parameters, $type), $route.', Link::to(), type '.$type);
                self::assertSame($semantic->getRouter()->generate($route, $parameters, $type), $semantic->__toLink($type), $route.', BaseTrait\'s router');
            }
            self::assertSame($router->generate($route, $parameters), $semantic->getUrl(), $route);
            self::assertSame($router->generate($route, $parameters), $semantic->__toLink(), $route);
            ++$checked;
        }
        self::assertGreaterThan(0, $checked, 'a route of the core was compared');

        // A route the router cannot make: null, as before.
        $semantic->setRouteName('no_such_route_c3e');
        self::assertNull($semantic->__toLink());

        if (null !== $router->getRouteCollection()->get('short_redirect')) {
            $short = (new \ReflectionClass(ShortLink::class))->newInstanceWithoutConstructor();
            self::assertNull($short->__toLink());
            $short->setSlug('x7Kq2');
            foreach ($types as $type) {
                self::assertSame($router->generate('short_redirect', ['slug' => 'x7Kq2'], $type), $short->__toLink([], $type), 'short link, type '.$type);
                self::assertSame($router->generate('short_redirect', ['utm' => 'mail', 'slug' => 'x7Kq2'], $type), $short->__toLink(['utm' => 'mail'], $type), 'short link with a parameter, type '.$type);
            }
            self::assertSame($router->generate('short_redirect', ['slug' => 'x7Kq2']), $short->__toLink());
        }
    }

    /** A registry without Urls is said aloud by Semantic, never taken for a missing route. */
    public function testALinkWithoutTheUrlsContractIsSaidAloud(): void
    {
        $semantic = (new \ReflectionClass(Semantic::class))->newInstanceWithoutConstructor();
        $semantic->setRouteName('security_login');
        $semantic->setRouteParameters([]);

        $container = Runtime::getContainer();
        Runtime::setContainer(null);
        try {
            $this->expectException(MissingContractException::class);
            $semantic->__toLink();
        } finally {
            Runtime::setContainer($container);
        }
    }
}
