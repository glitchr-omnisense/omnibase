<?php

namespace Base\Security;

use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;

/**
 * The sign-out's address, its token in it, for any host.
 *
 * A sign-out by a plain GET lets any other page sign a visitor out (an <img>
 * or a link to /logout is enough). Where the firewall's logout asks a CSRF
 * token (Symfony's `logout: { enable_csrf: true }`), the address a page gives
 * carries it: `/logout?_csrf_token=...`. The page /logout (security_logout,
 * SecurityController::Logout) keeps answering a prefetch with nothing, then
 * hands the token on to the firewall's path (security_logoutRequest), which
 * checks it. Where the firewall asks none, the token in the address is
 * harmless: the firewall reads nothing of it.
 *
 * The parameter and the intent are Symfony's defaults for the logout listener
 * (`csrf_parameter: _csrf_token`, `csrf_token_id: logout`), which a site does
 * not change.
 */
final class SignOut
{
    /** The page a sign-out link goes to (it hands on to REQUEST_ROUTE). */
    public const ROUTE = 'security_logout';

    /** The firewall's logout path. */
    public const REQUEST_ROUTE = 'security_logoutRequest';

    /** The CSRF token's intent the firewall's logout checks. */
    public const TOKEN_ID = 'logout';

    /** The query parameter the firewall's logout reads the token from. */
    public const PARAMETER = '_csrf_token';

    /** @return array{_csrf_token: string} the parameters a sign-out address carries */
    public static function parameters(Tokens $tokens): array
    {
        return [self::PARAMETER => $tokens->csrf(self::TOKEN_ID)];
    }

    /** The sign-out's address for the visitor's session, its token in it. */
    public static function path(Urls $urls, Tokens $tokens): string
    {
        return $urls->path(self::ROUTE, self::parameters($tokens));
    }
}
