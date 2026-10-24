<?php

namespace Tests\Base\Routing;

use Base\Routing\AdvancedRouterInterface;
use Base\Routing\Generator\AdvancedUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * An address has no empty segment: a variable given empty (path("app_wishlist",
 * {store: null}) in latoucheoriginale) made /boutique//wishlist where the router
 * does not check requirements (strict_requirements: null, the sites' dev and test),
 * once sanitize_url() no longer cleaned the first address of a route (4bf08f94),
 * and every later address had it already. The empty segment is dropped
 * (/boutique/wishlist), the first time and every time after; nothing else of
 * the address changes (the slash before a query stays out, FirstAddressTest).
 */
final class EmptySegmentTest extends KernelTestCase
{
    public function testTheHelperDropsEmptySegmentsOfThePathOnly(): void
    {
        self::assertSame('/shop/shelf', AdvancedUrlGenerator::withoutEmptySegments('/shop//shelf'));
        self::assertSame('/shop/shelf/', AdvancedUrlGenerator::withoutEmptySegments('/shop///shelf/'));
        self::assertSame('https://localhost/shop/shelf?next=https://x.test//a#//b', AdvancedUrlGenerator::withoutEmptySegments('https://localhost/shop//shelf?next=https://x.test//a#//b'));
        self::assertSame('/wishlist', AdvancedUrlGenerator::withoutEmptySegments('//wishlist'));
        self::assertSame('//cdn.test/shop/shelf', AdvancedUrlGenerator::withoutEmptySegments('//cdn.test/shop//shelf', UrlGeneratorInterface::NETWORK_PATH));
        self::assertSame('/shop/shelf?page=2', AdvancedUrlGenerator::withoutEmptySegments('/shop/shelf?page=2'));
    }

    public function testANullSegmentTakesTheRouteWithoutIt(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        static::getContainer()->get('request_stack')->push(Request::create('https://localhost/'));
        $router = static::getContainer()->get(AdvancedRouterInterface::class);
        if (!$router->useAdvancedFeatures() || null === $router->getRouteCollection()->get('demo_shelf.en')) {
            self::markTestSkipped('The harness\'s demo_shelf routes and the advanced router are needed.');
        }

        $generator = $router->getGenerator();
        $generator->setStrictRequirements(null);
        try {
            foreach ([UrlGeneratorInterface::ABSOLUTE_PATH, UrlGeneratorInterface::ABSOLUTE_URL] as $type) {
                (new \ReflectionProperty($generator, 'cachedRoutes'))->setValue($generator, []);
                foreach ([1, 2] as $time) {
                    $empty = $router->generate('demo_shelf', ['store' => null], $type);
                    self::assertStringEndsWith('/shop/shelf', $empty, $time.' '.$type);
                    self::assertStringNotContainsString('//shelf', $empty);
                    self::assertStringEndsWith('/shop/paris/shelf', $router->generate('demo_shelf', ['store' => 'paris'], $type), $time.' '.$type);
                    self::assertStringEndsWith('/shop/shelf?page=2', $router->generate('demo_shelf', ['store' => '', 'page' => 2], $type), $time.' '.$type);
                }
            }
        } finally {
            $generator->setStrictRequirements(true);
        }
    }
}
