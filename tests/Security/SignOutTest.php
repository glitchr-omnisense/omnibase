<?php

namespace Base\Tests\Security;

use Base\Security\SignOut;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use PHPUnit\Framework\TestCase;

/**
 * The sign-out's address carries the token the firewall's logout asks, under
 * Symfony's default names (`_csrf_token`, intent `logout`), through the
 * contracts only.
 */
class SignOutTest extends TestCase
{
    public function testTheAddressCarriesTheTokenOfTheLogoutIntent(): void
    {
        $urls = new class implements Urls {
            public function path(string $name, array $parameters = []): string
            {
                return '/'.$name.($parameters ? '?'.http_build_query($parameters) : '');
            }

            public function url(string $name, array $parameters = []): string
            {
                return 'https://localhost'.$this->path($name, $parameters);
            }

            public function asset(string $path): string
            {
                return '/'.$path;
            }
        };
        $tokens = new class implements Tokens {
            public function csrf(string $intent): string
            {
                return 'token-of-'.$intent;
            }

            public function isValid(string $intent, ?string $token): bool
            {
                return $token === $this->csrf($intent);
            }
        };

        self::assertSame(['_csrf_token' => 'token-of-logout'], SignOut::parameters($tokens));
        self::assertSame('/security_logout?_csrf_token=token-of-logout', SignOut::path($urls, $tokens));
        self::assertSame(['security_logout', 'security_logoutRequest'], [SignOut::ROUTE, SignOut::REQUEST_ROUTE], 'the routes of SecurityController');
        self::assertSame([SignOut::ROUTE, SignOut::REQUEST_ROUTE], [\Base\Security\LoginFormAuthenticator::LOGOUT_ROUTE, \Base\Security\LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE]);
    }
}
