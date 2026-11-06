<?php

namespace Base\Request\Rule;

use Base\Entity\User;
use Base\Request\Decision;
use Base\Request\Page;
use Omnibridge\Contract\Urls;

/**
 * An account whose address is not verified yet (plan "omnibase agnostic", step F5e, slice 2; formerly
 * omnibridge/symfony's SecuritySubscriber::onKernelRequest()): told so on every page but the account's own
 * ("security_*") - that the e-mail was just sent (the verification's token still under its delay), or where
 * to ask for it. A tester's account is left alone.
 *
 * The former subscriber also wrote a redirection to the profile there, which could never happen: it was
 * reached only outside the account's pages, where its own condition always chose the notice.
 */
final class Verification
{
    public const ROUTE = 'security_verifyEmail';

    public function __construct(private readonly Urls $urls)
    {
    }

    public function decide(User $user, Page $page): Decision
    {
        if ($user->isVerified() || $user->isTester() || $page->isSecured()) {
            return Decision::continue();
        }
        $token = $user->getToken('verify-email');
        if ($token && $token->hasVeto()) {
            return Decision::continue()->withNotice('verifyEmail.alreadySent', [$token->getThrottleTimeStr()], 'info');
        }

        return Decision::continue()->withNotice('verifyEmail.pending', [$this->urls->path(self::ROUTE)], 'warning');
    }
}
