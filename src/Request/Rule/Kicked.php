<?php

namespace Base\Request\Rule;

use Base\Request\Decision;
use Base\Request\Page;
use Base\Security\SignOut;
use Omnibridge\Contract\Tokens;

/**
 * An account an administrator kicked out (plan "omnibase agnostic", step F5e, slice 2; formerly
 * omnibridge/symfony's SecuritySubscriber::onKernelRequest()): told so, sent to the sign-out with the token
 * the sign-out asks (Base\Security\SignOut: the server signs it out itself), the address it was asking kept
 * for after its next sign-in. Nothing is written: the next sign-in lifts the kick.
 */
final class Kicked
{
    /** The sign-out's request page (omnibridge/symfony's LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE). */
    public const ROUTE = 'security_logoutRequest';

    public function __construct(private readonly ?Tokens $tokens = null)
    {
    }

    public function decide(object $user, Page $page): Decision
    {
        if (!method_exists($user, 'isKicked') || !$user->isKicked()) {
            return Decision::continue();
        }

        return Decision::redirect(self::ROUTE, null !== $this->tokens ? SignOut::parameters($this->tokens) : [])
            ->withNotice('kickout', [$user], 'warning')
            ->withRemember($page->uri());
    }
}
