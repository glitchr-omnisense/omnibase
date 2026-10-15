<?php

namespace Tests\Base\Http;

use Base\Service\Referrer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A site behind a proxy that asks for a basic authentication (a beta): the
 * request carries the user and the password (Authorization, PHP_AUTH_USER,
 * PHP_AUTH_PW). No address the core makes from it - a redirection, the
 * current address, an absolute link, the address to come back to after
 * signing in - carries them.
 */
class BasicAuthUrlHttpTest extends KernelTestCase
{
    use HttpTestTrait;

    private const USER = 'beta';
    private const PASSWORD = 's3cret';

    private array $server = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->bootHost();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        parent::tearDown();
    }

    private function credentials(): array
    {
        return [
            'HTTP_HOST' => 'localhost',
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode(self::USER.':'.self::PASSWORD),
            'PHP_AUTH_USER' => self::USER,
            'PHP_AUTH_PW' => self::PASSWORD,
            'AUTH_TYPE' => 'Basic',
        ];
    }

    private function assertNoCredentials(?string $url, string $what): void
    {
        $url = (string) $url;
        $this->assertStringNotContainsString(self::USER.':', $url, $what.': '.$url);
        $this->assertStringNotContainsString(self::PASSWORD, $url, $what.': '.$url);
        $this->assertDoesNotMatchRegularExpression('#^[a-z][a-z0-9+.-]*://[^/?\#]*@#i', $url, $what.': '.$url);
    }

    public function testNoAddressCarriesTheCredentials(): void
    {
        $router = static::getContainer()->get('router');
        if (!$router->getRouteCollection()->get('security_login') || !$router->getRouteCollection()->get('security_verifyEmail')) {
            self::markTestSkipped('The sign-in page and a page that asks to sign in are needed.');
        }

        // As PHP's globals hold them too (get_url() reads them out of a request)
        $_SERVER = array_merge($_SERVER, $this->credentials(), ['REQUEST_URI' => '/verify-email']);

        // The redirection to the sign-in page
        $response = $this->request('/verify-email', null, 'GET', [], $this->credentials());
        $this->assertSame(302, $response->getStatusCode());
        $this->assertNoCredentials($response->headers->get('Location'), 'the redirection');
        $this->assertNoCredentials(get_url(), 'the current address, from the globals');

        // The addresses made while the request is handled
        $request = Request::create('http://localhost/verify-email', 'GET', [], [], [], $this->credentials());
        $this->assertSame(self::USER, $request->getUser(), 'the request does carry them');
        $request->setSession(static::getContainer()->get('session.factory')->createSession());

        $stack = static::getContainer()->get('request_stack');
        $stack->push($request);
        try {
            $this->assertNoCredentials(get_url(), 'the current address');
            $this->assertNoCredentials(compose_url(domain: 'localhost', path: '/login'), 'an address composed for the site');
            $this->assertNoCredentials($router->generate('security_login', [], UrlGeneratorInterface::ABSOLUTE_URL), 'an absolute link');
            $this->assertNoCredentials($request->getUri(), 'the address to come back to');

            // An address to come back to stored with credentials gives them back no more.
            $request->getSession()->set('_target_path', 'http://'.self::USER.':'.self::PASSWORD.'@localhost/verify-email');
            $referrer = static::getContainer()->get(Referrer::class);
            $this->assertNotNull($referrer->getUrl());
            $this->assertNoCredentials($referrer->getUrl(), 'the address to come back to, once stored');
        } finally {
            $stack->pop();
        }
    }
}
