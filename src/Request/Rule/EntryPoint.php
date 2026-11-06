<?php

namespace Base\Request\Rule;

use Base\Request\Decision;

/**
 * A page refused to the visitor (plan "omnibase agnostic", step F5e, slice 2): who is not signed in is sent to
 * the sign-in - the rescue sign-in where the access restriction makes an exception of the page
 * (EXCEPTION_ACCESS) -, the address kept for after it: what Symfony's firewall does through its entry point
 * (omnibridge/symfony's LoginFormAuthenticator::start(), which asks this rule); who is signed in is refused
 * (403).
 */
final class EntryPoint
{
    public const ROUTE = 'security_login';
    public const RESCUE_ROUTE = 'security_rescue';

    public static function decide(bool $signedIn, ?string $uri = null, bool $exception = false): Decision
    {
        if ($signedIn) {
            return Decision::deny(403);
        }
        $decision = Decision::redirect($exception ? self::RESCUE_ROUTE : self::ROUTE);

        return null !== $uri ? $decision->withRemember($uri) : $decision;
    }
}
