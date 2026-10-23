<?php

namespace Base\Auth;

use Omniauth\OAuth\Contract\EmailLinking;
use Omniauth\OAuth\Identity;

/**
 * omniauth's rule for a link by a verified address, on omnibase's
 * Policy (QA3): never for an account with a second factor or an
 * administration role, nor a demonstration account; $also adds another rule
 * (omniauth's NoSecondFactorLinking, which reads the methods mounted).
 */
class PolicyLinking implements EmailLinking
{
    public function __construct(
        protected readonly Policy $policy,
        protected readonly ?EmailLinking $also = null,
    ) {
    }

    public function allows(object $account, Identity $identity): bool
    {
        return $this->policy->allowsEmailLinking($account) && ($this->also?->allows($account, $identity) ?? true);
    }
}
