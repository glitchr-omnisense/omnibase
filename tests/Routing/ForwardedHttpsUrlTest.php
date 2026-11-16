<?php

namespace Tests\Base\Routing;

use Base\Routing\AdvancedRouterInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Base\Http\HttpTestTrait;

/**
 * A site served by FrankenPHP behind a proxy that terminates TLS: PHP gets
 * HTTPS = "" (empty, not missing) and the proxy says X-Forwarded-Proto:
 * https. get_url() read `$_SERVER['HTTPS'] ?? ...`, so the empty string
 * stopped the chain and every address came out http - on a port other than
 * 443 every page then redirected to port 80. The scheme and the port are the
 * request's now (Request::isSecure(), the forwarded host or port), as soon as
 * framework.trusted_proxies trusts the proxy; without one, nothing changes.
 */
class ForwardedHttpsUrlTest extends KernelTestCase
{
    use HttpTestTrait;

    private array $server = [];
    private array $env = [];
    private array $trustedProxies = [];
    private int $trustedHeaderSet = 0;

    private const FORWARDED = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->env = $_ENV;
        $this->trustedProxies = Request::getTrustedProxies();
        $this->trustedHeaderSet = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->env;
        Request::setTrustedProxies($this->trustedProxies, $this->trustedHeaderSet);
        parent::tearDown();
    }

    /** What FrankenPHP hands PHP behind nginx: plain HTTP on :80, HTTPS empty. */
    private function frankenphp(array $headers): array
    {
        return array_merge([
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTPS' => '',
            'REQUEST_SCHEME' => 'http',
            'REMOTE_ADDR' => '172.18.0.5',
            'REQUEST_URI' => '/somewhere',
        ], $headers);
    }

    private function globals(array $server): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['USE_HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_PORT'], $_SERVER['HTTP_X_FORWARDED_HOST']);
        $_SERVER = array_merge($_SERVER, $server);
    }

    public function testAForwardedHttpsIsHttpsOnTheForwardedPort(): void
    {
        Request::setTrustedProxies(['172.16.0.0/12'], self::FORWARDED);
        $this->globals($this->frankenphp(['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_PORT' => '8663']));

        $this->assertSame('https://localhost:8663/somewhere', get_url());
        $this->assertSame('https', get_url_scheme());
        $this->assertStringStartsWith('https://', compose_url(domain: 'localhost', path: '/x'), 'and so is an address composed without a scheme');
    }

    public function testTheForwardedHostsPortComesBeforeTheProxysOwn(): void
    {
        // nginx in a container published on 8663: the browser's Host
        // ($http_host) says 8663, X-Forwarded-Port says the 443 nginx listens on.
        Request::setTrustedProxies(['172.16.0.0/12'], self::FORWARDED);
        $this->globals($this->frankenphp([
            'HTTP_HOST' => 'localhost:8663',
            'HTTP_X_FORWARDED_HOST' => 'localhost:8663',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]));

        $this->assertSame('https://localhost:8663/somewhere', get_url());

        // A forwarded host without a port leaves it to X-Forwarded-Port, and 443 is not written.
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'localhost';
        $this->assertSame('https://localhost/somewhere', get_url());
    }

    public function testWithoutATrustedProxyNothingChanges(): void
    {
        // The same request, from a proxy nobody trusts: HTTPS "" is http, the
        // forwarded headers are not read, the port is the Host's or SERVER_PORT.
        Request::setTrustedProxies([], self::FORWARDED);
        $this->globals($this->frankenphp(['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_PORT' => '8663']));
        $this->assertSame('http://localhost/somewhere', get_url());

        $_SERVER['HTTP_HOST'] = 'localhost:8663';
        $this->assertSame('http://localhost:8663/somewhere', get_url());

        $_SERVER['HTTPS'] = 'off';
        $this->assertSame('http', get_url_scheme());
        $this->assertStringStartsWith('http://', compose_url(domain: 'localhost', path: '/x'), '"off" read as a boolean was https');

        $_SERVER['HTTPS'] = 'on';
        $this->assertSame('https://localhost:8663/somewhere', get_url());

        // No HTTPS at all: the variables the core always read decide, as before.
        unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME']);
        $this->assertSame('https', get_url_scheme(), 'X-Forwarded-Proto, read when nothing else says');
        $_SERVER['USE_HTTPS'] = 'off';
        $this->assertSame('http', get_url_scheme());
    }

    public function testThroughTheKernelNothingRedirectsToPort80(): void
    {
        $this->bootHost();

        $router = static::getContainer()->get(AdvancedRouterInterface::class);
        if (!$router->useAdvancedFeatures() || !$router->getRouteCollection()->get('security_login')) {
            self::markTestSkipped('The advanced router and its sign-in page are needed.');
        }

        // The ports the site declares (HTTPS_PORT / HTTP_PORT), 8663 among them.
        $_SERVER['HTTPS_PORT'] = $_ENV['HTTPS_PORT'] = '[443,8663]';
        $_SERVER['HTTP_PORT'] = $_ENV['HTTP_PORT'] = '[80,8663]';

        Request::setTrustedProxies(['172.16.0.0/12'], self::FORWARDED);
        $server = $this->frankenphp(['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_PORT' => '8663', 'REQUEST_URI' => '/login']);
        $this->globals($server);

        $response = static::$kernel->handle(new Request([], [], [], [], [], $server));
        $this->assertSame(200, $response->getStatusCode(), 'the page answers, it is not sent elsewhere: '.$response->headers->get('Location'));

        // A page that asks to sign in sends to the sign-in page on https:8663.
        $response = static::$kernel->handle(new Request([], [], [], [], [], array_merge($server, ['REQUEST_URI' => '/verify-email'])));
        $this->assertSame(302, $response->getStatusCode());
        $location = (string) $response->headers->get('Location');
        $this->assertMatchesRegularExpression('#^(/|https://localhost:8663/)#', $location);
        $this->assertStringNotContainsString('http://', $location);
        $this->assertStringNotContainsString(':80/', $location);

        // The addresses generated while the request is handled.
        $request = new Request([], [], [], [], [], $server);
        $request->setSession(static::getContainer()->get('session.factory')->createSession());
        $stack = static::getContainer()->get('request_stack');
        $stack->push($request);
        try {
            $this->assertSame('https://localhost:8663/login', get_url());
            $this->assertStringStartsWith('https://localhost:8663/', $router->generate('security_login', [], UrlGeneratorInterface::ABSOLUTE_URL));
        } finally {
            $stack->pop();
        }
    }
}
