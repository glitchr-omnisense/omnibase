<?php

namespace Tests\Base\Routing;

use Base\Routing\AdvancedRouter;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\ParameterBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Base\Http\HttpTestTrait;

/**
 * AdvancedRouter::getScheme() is the scheme of the request being handled.
 * It read `$_SERVER["REQUEST_SCHEME"] ?? true` as a boolean, and "http" is a
 * true string: https whatever the request. The addresses of Symfony's own
 * routes (here `_switch_locale`, a route the generator hands Symfony as it
 * does `_profiler` and `_wdt`) said https on a page served over http, and
 * the host fallbacks' ports of an http request were filtered by HTTPS_PORT
 * while getPort() judged the visited port by HTTP_PORT. It now follows the
 * main request (get_url_scheme(): Request::isSecure(), a trusted proxy's
 * X-Forwarded-Proto), and keeps https where there is none (a command).
 */
class RouterSchemeTest extends KernelTestCase
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

    private function router(): AdvancedRouterInterface
    {
        $this->bootHost();

        $router = static::getContainer()->get(AdvancedRouterInterface::class);
        if (!$router->useAdvancedFeatures() || !$router->getRouteCollection()->get('security_login')) {
            self::markTestSkipped('The advanced router and its sign-in page are needed.');
        }

        // PHP's globals say nothing of the scheme: the request handled does.
        foreach (['HTTPS', 'USE_HTTPS', 'REQUEST_SCHEME', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_PORT', 'HTTP_X_FORWARDED_HOST', 'HTTP_PORT', 'HTTPS_PORT', 'HTTP_REDUCTION'] as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
        }
        $_SERVER['HTTP_HOST'] = 'localhost';

        return $router;
    }

    /**
     * The host fallbacks of a site's base.yaml, as every site declares them
     * (`port: "%env(json:HTTP_PORT)%"`), turned on for this router: the
     * harness declares none.
     */
    private function fallbacks(AdvancedRouterInterface $router, array $ports): void
    {
        $fallbacks = [['locale' => null, 'env' => null, 'reduction' => true, 'machine' => [], 'subdomain' => [], 'domain' => ['localhost'], 'port' => $ports, 'base_dir' => '/']];
        $bag = new class(static::getContainer(), $fallbacks) extends ParameterBag {
            public function __construct($container, private array $fallbacks)
            {
                parent::__construct($container);
            }

            public function get(string $path = '', ?array $bag = null): array|bool|string|int|float|null
            {
                return match ($path) {
                    'base.router.use_fallbacks' => true,
                    'base.router.host_fallbacks' => $this->fallbacks,
                    default => parent::get($path, $bag),
                };
            }
        };

        foreach (['parameterBag' => $bag, 'useFallbacks' => true] as $property => $value) {
            $reflection = new \ReflectionProperty(AdvancedRouter::class, $property);
            $reflection->setValue($router, $value);
        }
    }

    /** @param callable(AdvancedRouterInterface): void $assertions */
    private function during(AdvancedRouterInterface $router, Request $request, callable $assertions): void
    {
        $request->setSession(static::getContainer()->get('session.factory')->createSession());
        $stack = static::getContainer()->get('request_stack');
        $stack->push($request);
        // What RouterListener does with a request the kernel handles.
        $context = clone $router->getContext();
        $router->getContext()->fromRequest($request);
        try {
            $assertions($router);
        } finally {
            $stack->pop();
            $router->setContext($context);
        }
    }

    /** What FrankenPHP hands PHP behind nginx terminating TLS: plain HTTP, HTTPS empty. */
    private function frankenphp(array $headers): array
    {
        return array_merge([
            'HTTP_HOST' => 'localhost:8663',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTPS' => '',
            'REQUEST_SCHEME' => 'http',
            'REMOTE_ADDR' => '172.18.0.5',
            'REQUEST_URI' => '/login',
        ], $headers);
    }

    public function testAnHttpRequestIsHttp(): void
    {
        $router = $this->router();

        $this->during($router, Request::create('http://localhost/login'), function (AdvancedRouterInterface $router) {
            $this->assertSame('http', $router->getScheme(), 'REQUEST_SCHEME "http" read as a boolean was https');
            $this->assertStringStartsWith('http://localhost/', $router->generate('_switch_locale', ['_locale' => 'en'], UrlGeneratorInterface::ABSOLUTE_URL), 'and so is an address of a route Symfony generates');
            $this->assertStringStartsWith('http://localhost/', $router->generate('security_login', [], UrlGeneratorInterface::ABSOLUTE_URL));
            $this->assertStringStartsNotWith('http', $router->generate('security_login'), 'a path stays a path');
        });
    }

    public function testAnHttpsRequestIsHttps(): void
    {
        $router = $this->router();

        $this->during($router, Request::create('https://localhost/login'), function (AdvancedRouterInterface $router) {
            $this->assertSame('https', $router->getScheme());
            $this->assertStringStartsWith('https://localhost/', $router->generate('_switch_locale', ['_locale' => 'en'], UrlGeneratorInterface::ABSOLUTE_URL));
            $this->assertStringStartsWith('https://localhost/', $router->generate('security_login', [], UrlGeneratorInterface::ABSOLUTE_URL));
        });
    }

    public function testAProxyTerminatingTlsIsHttpsWhenTrusted(): void
    {
        $router = $this->router();
        $server = $this->frankenphp(['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_PORT' => '8663', 'HTTP_X_FORWARDED_HOST' => 'localhost:8663']);

        // lesringostars: nginx on 8663 in front of FrankenPHP, trusted.
        Request::setTrustedProxies(['172.16.0.0/12'], self::FORWARDED);
        $this->during($router, new Request([], [], [], [], [], $server), function (AdvancedRouterInterface $router) {
            $this->assertSame('https', $router->getScheme());
            $this->assertStringStartsWith('https://', $router->generate('_switch_locale', ['_locale' => 'en'], UrlGeneratorInterface::ABSOLUTE_URL));
        });

        // The same headers from an address nobody trusts are not read.
        Request::setTrustedProxies([], self::FORWARDED);
        $this->during($router, new Request([], [], [], [], [], $server), function (AdvancedRouterInterface $router) {
            $this->assertSame('http', $router->getScheme());
        });
    }

    public function testWithoutARequestItIsHttpsUnlessTheSiteSaysOtherwise(): void
    {
        $router = $this->router();
        $this->assertNull(static::getContainer()->get('request_stack')->getMainRequest());

        // A command, a worker: PHP's globals hold no scheme, links stay https.
        $this->assertSame('https', $router->getScheme());
        $this->assertStringStartsWith('https://', $router->generate('_switch_locale', ['_locale' => 'en'], UrlGeneratorInterface::ABSOLUTE_URL));

        $_ENV['HTTPS'] = '';
        $this->assertSame('https', $router->getScheme(), 'an empty HTTPS says nothing');
        $_ENV['HTTPS'] = 'on';
        $this->assertSame('https', $router->getScheme());
        $_ENV['HTTPS'] = 'off';
        $this->assertSame('http', $router->getScheme(), 'a site served over http says so');
        unset($_ENV['HTTPS']);
        $_SERVER['USE_HTTPS'] = 'false';
        $this->assertSame('http', $router->getScheme());
    }

    public function testAnHttpSiteOnAPortOfItsOwnKeepsItsPortAndScheme(): void
    {
        // latoucheoriginale's tests: http on 8081 (HTTP_PORT='[8081]', no
        // HTTPS_PORT), its fallbacks' port that one. Filtered by HTTPS_PORT
        // ([443]) the fallback port was 443, so the IP restriction's
        // redirection went to https://localhost/, port and scheme lost.
        $router = $this->router();
        $_SERVER['HTTP_PORT'] = $_ENV['HTTP_PORT'] = '[8081]';
        $_SERVER['HTTP_REDUCTION'] = $_ENV['HTTP_REDUCTION'] = 'true';
        $this->fallbacks($router, [8081]);

        $this->during($router, Request::create('http://localhost:8081/login'), function (AdvancedRouterInterface $router) {
            $this->assertSame('http', $router->getScheme());
            $this->assertSame([8081], array_values($router->getPortFallbacks()));
            $this->assertSame(8081, $router->getPortFallback());
            $this->assertSame(8081, $router->getPort());
            $this->assertStringStartsWith('http://localhost:8081/', $router->generate('security_login', [], UrlGeneratorInterface::ABSOLUTE_URL));
            $this->assertStringStartsWith('http://localhost:8081/', $router->generate('_switch_locale', ['_locale' => 'en'], UrlGeneratorInterface::ABSOLUTE_URL));
        });

        $response = static::$kernel->handle(Request::create('http://localhost:8081/login'));
        $this->assertSame(200, $response->getStatusCode(), 'the page answers on its port: '.$response->headers->get('Location'));
    }

    public function testTheFallbacksPortsAreTheRequestsSchemes(): void
    {
        // Every other site: HTTP_PORT='[80,8094]', HTTPS_PORT='[443,8473]', the
        // fallbacks' port HTTP_PORT. An https request keeps the https ports,
        // as before; an http one the http ports, as getPort() judges it.
        $router = $this->router();
        $_SERVER['HTTP_PORT'] = $_ENV['HTTP_PORT'] = '[80,8094]';
        $_SERVER['HTTPS_PORT'] = $_ENV['HTTPS_PORT'] = '[443,8473]';
        $this->fallbacks($router, [80, 8094]);

        $this->during($router, Request::create('https://localhost:8473/login'), function (AdvancedRouterInterface $router) {
            $this->assertSame([443, 8473], array_values($router->getPortFallbacks()));
            $this->assertSame(8473, $router->getPort());
        });

        $this->during($router, Request::create('http://localhost:8094/login'), function (AdvancedRouterInterface $router) {
            $this->assertSame([80, 8094], array_values($router->getPortFallbacks()));
            $this->assertNull($router->getPortFallback(), '80 is http\'s own');
            $this->assertSame(8094, $router->getPort());
        });
    }
}
