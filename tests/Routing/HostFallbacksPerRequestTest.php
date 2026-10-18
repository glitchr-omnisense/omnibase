<?php

namespace Tests\Base\Routing;

use Base\Routing\AdvancedRouterInterface;
use Base\Service\ArrayParameterBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The host fallbacks are worked out once per main request (AdvancedRouter::getFallbackParameters(),
 * asked at every address a page makes): the same within a request, worked out again for the next
 * main request and for another locale, and as before without a request.
 */
final class HostFallbacksPerRequestTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
    }

    private static function fallbacks(AdvancedRouterInterface $router, ?string $locale = null): ?array
    {
        return (new \ReflectionMethod($router, 'getFallbackParameters'))->invoke($router, $locale);
    }

    public function testOncePerMainRequest(): void
    {
        $router = static::getContainer()->get(AdvancedRouterInterface::class);
        // On, whatever the application says, with fallbacks of their own (two locales, ports per
        // scheme): what is compared is the memo, not the application's fallbacks.
        (new \ReflectionProperty($router, 'useFallbacks'))->setValue($router, true);
        (new \ReflectionProperty($router, 'parameterBag'))->setValue($router, new ArrayParameterBag(['base.router.host_fallbacks' => [
            ['locale' => 'fr', 'machine' => [''], 'subdomain' => ['m'], 'domain' => ['exemple.fr'], 'port' => [443, 8443, 8080]],
            ['locale' => 'en', 'machine' => [''], 'subdomain' => [''], 'domain' => ['example.com'], 'port' => [443, 80]],
        ]]));
        $resolve = new \ReflectionMethod($router, 'resolveFallbackParameters');
        $requests = static::getContainer()->get('request_stack');

        $withoutRequest = self::fallbacks($router);
        self::assertSame($resolve->invoke($router, null, null), $withoutRequest);

        $requests->push(Request::create('https://localhost/'));
        $first = self::fallbacks($router);
        self::assertSame($resolve->invoke($router, null, null), $first);
        self::assertSame($first, self::fallbacks($router), 'the same within the request');
        foreach (['en', 'fr'] as $locale) {
            self::assertSame($resolve->invoke($router, $locale, null), self::fallbacks($router, $locale), $locale);
        }
        $requests->pop();

        $requests->push(Request::create('http://localhost:8080/'));
        self::assertSame($resolve->invoke($router, null, null), self::fallbacks($router), 'worked out again for the next request');
        self::assertNotSame($first['port'] ?? null, self::fallbacks($router)['port'] ?? null, 'the http request\'s ports, not the first one\'s');
        $requests->pop();
    }
}
