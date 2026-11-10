<?php

namespace Tests\Base\Http;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A request whose address matches a route its method does not allow, through the host application's whole kernel
 * (omnibase's router and matcher, its error controller): 405 Method Not Allowed with the route's methods in `Allow`,
 * as Symfony's own router answers it, and omnibridge/laravel's. It answered 404: omnibridge/symfony's
 * AdvancedUrlMatcher retried the address with a trailing slash and kept that retry's "no route"
 * (omnibridge/parity's former `method-not-allowed` difference). The allowed method still reaches the action; an
 * address no route matches is still a 404.
 */
class MethodNotAllowedHttpTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        self::bootKernel();
    }

    /** A visitor's request through the whole kernel (no session, no account). */
    private function request(string $path, string $method = 'GET'): Response
    {
        $kernel = static::$kernel;
        \assert(null !== $kernel);

        return $kernel->handle(Request::create($path, $method));
    }

    public function testAMethodTheRouteDoesNotAllowIs405WithAllow(): void
    {
        // Base\Controller\Action\Notification\Read: POST only.
        foreach (['GET', 'PUT'] as $method) {
            $response = $this->request('/notifications/read', method: $method);

            $this->assertSame(405, $response->getStatusCode(), "$method /notifications/read");
            $this->assertSame('POST', $response->headers->get('Allow'), "$method /notifications/read: Allow");
        }
    }

    public function testAnAddressNoRouteMatchesIsStillNotFound(): void
    {
        $this->assertSame(404, $this->request('/no/such/address/'.bin2hex(random_bytes(4)))->getStatusCode());
    }

    public function testTheAllowedMethodReachesTheAction(): void
    {
        $this->assertNotContains($this->request('/notifications/read', method: 'POST')->getStatusCode(), [404, 405]);
    }
}
