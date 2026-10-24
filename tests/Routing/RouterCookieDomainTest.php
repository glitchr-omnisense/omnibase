<?php

namespace Tests\Base\Routing;

use Base\Routing\AdvancedRouterInterface;
use Base\Routing\CookieDomain;
use Base\Service\ArrayParameterBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Symfony's router gives its cookies' domain by CookieDomain's rule (step
 * R29): the domain the browser's PHPUSERID is written for (UserTracker), the
 * one omnibridge/laravel's sign-in effects write it for under Laravel - the
 * request's domain when configured, else the first configured.
 */
final class RouterCookieDomainTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
    }

    public function testTheRoutersDomainIsTheRulesOnTheConfiguredDomains(): void
    {
        $router = static::getContainer()->get(AdvancedRouterInterface::class);
        (new \ReflectionProperty($router, 'useFallbacks'))->setValue($router, true);
        (new \ReflectionProperty($router, 'parameterBag'))->setValue($router, new ArrayParameterBag(['base.router.host_fallbacks' => [
            ['locale' => null, 'machine' => [''], 'subdomain' => ['', 'm'], 'domain' => ['lesringostars.symfony.test', 'localhost'], 'port' => [443]],
        ]]));
        $requests = static::getContainer()->get('request_stack');

        foreach (['https://lesringostars.symfony.test/japon' => 'lesringostars.symfony.test', 'https://localhost/' => 'localhost', 'https://example.org/' => 'lesringostars.symfony.test'] as $url => $domain) {
            $requests->push(Request::create($url));
            self::assertSame($domain, $router->getDomain(), $url);
            self::assertSame(CookieDomain::of($url, $router->getDomainFallbacks()), $router->getDomain(), $url);
            $requests->pop();
        }
    }
}
