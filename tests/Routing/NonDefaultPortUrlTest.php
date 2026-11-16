<?php

namespace Tests\Base\Routing;

use Base\Routing\AdvancedRouterInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Base\Http\HttpTestTrait;

/**
 * A site served over http on a port of its own (HTTP_PORT='[8081]', no
 * HTTPS_PORT): an address on that port is its address. The router's reduction
 * (HTTP_REDUCTION) rebuilds the visited address with AdvancedRouter::getPort(),
 * which judged the request's port by the router's scheme - getScheme(), https
 * whatever the request - against HTTPS_PORT ([443]): 8081 was dropped, and
 * http://localhost:8081/x redirected to http://localhost/x, port 80. Since
 * get_url() reads the request the kernel handles, every such request did.
 */
class NonDefaultPortUrlTest extends KernelTestCase
{
    use HttpTestTrait;

    private array $server = [];
    private array $env = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->env = $_ENV;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->env;
        parent::tearDown();
    }

    private function site(string $httpPorts): AdvancedRouterInterface
    {
        $this->bootHost();

        $router = static::getContainer()->get(AdvancedRouterInterface::class);
        if (!$router->useAdvancedFeatures() || !$router->getRouteCollection()->get('security_login')) {
            self::markTestSkipped('The advanced router and its sign-in page are needed.');
        }

        // The ports the site declares (a site's .env), its address reduced to them.
        $_SERVER['HTTP_PORT'] = $_ENV['HTTP_PORT'] = $httpPorts;
        unset($_SERVER['HTTPS_PORT'], $_ENV['HTTPS_PORT']);
        $_SERVER['HTTP_REDUCTION'] = $_ENV['HTTP_REDUCTION'] = 'true';

        // What the test before it may have left in PHP's globals (an address
        // without a port); the request handled is what counts.
        $_SERVER['HTTP_HOST'] = 'localhost';
        unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['USE_HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

        return $router;
    }

    /** @return iterable<string, array{string}> */
    public static function ports(): iterable
    {
        yield 'the only port declared' => ['[8081]'];
        yield 'a port declared after 80' => ['[80,8081]'];
    }

    /** @dataProvider ports */
    public function testAPageOnItsPortIsNotSentToPort80(string $httpPorts): void
    {
        $this->site($httpPorts);

        $response = static::$kernel->handle(Request::create('http://localhost:8081/login'));
        $this->assertSame(200, $response->getStatusCode(), 'the page answers on its port, it is not sent elsewhere: '.$response->headers->get('Location'));
    }

    /** @dataProvider ports */
    public function testTheRequestsPortIsTheRoutersAndItsLinks(string $httpPorts): void
    {
        $router = $this->site($httpPorts);

        $request = Request::create('http://localhost:8081/login');
        $request->setSession(static::getContainer()->get('session.factory')->createSession());
        $stack = static::getContainer()->get('request_stack');
        $stack->push($request);
        try {
            $this->assertSame('http://localhost:8081/login', get_url());
            $this->assertSame(8081, $router->getPort());
            $this->assertStringStartsWith('http://localhost:8081/', $router->generate('security_login', [], UrlGeneratorInterface::ABSOLUTE_URL));
        } finally {
            $stack->pop();
        }
    }

    public function testAPortTheSiteDoesNotDeclareIsStillReduced(): void
    {
        $router = $this->site('[8081]');

        $request = Request::create('http://localhost:9999/login');
        $stack = static::getContainer()->get('request_stack');
        $stack->push($request);
        try {
            $this->assertSame(8081, $router->getPort(), 'the declared port, not the one asked');
        } finally {
            $stack->pop();
        }
    }
}
