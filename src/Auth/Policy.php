<?php

namespace Base\Auth;

use Base\Service\SecurityPolicy;
use Omniauth\Context;
use Omniauth\Contract\SecondFactorPolicy;
use Omniauth\Event\Revoking;
use Omniauth\Factor;

/**
 * omnibase's rule around omniauth's (plan "omniauth", §1.6, step AU4 - the
 * former P4 of the study): Base\Service\SecurityPolicy, asked of the account
 * signing in, decorating the second-factor rule omniauth is given
 * (EnrolledSecondFactors: a second factor after a password, a link or an
 * identity provider, for an account that set one up).
 *
 * - A demonstration account (the `demo` environment) is never asked for a
 *   second factor: every visitor signs in with it.
 * - An account a second factor is required of (the administrator's
 *   "mandatory", or one of base.security.two_factor.required_roles: the
 *   administration roles of a site) that has none is signed in, then sent to
 *   enrol, as today: needsEnrolment() is what the host's enrolment prompt
 *   asks (SecurityEnrolmentSubscriber under Symfony).
 * - "The administrator wins" when a factor is turned off: onRevoking(), a
 *   listener of omniauth's Revoking event, refuses to turn off the last
 *   second factor of an account that must keep one (`two-factor-mandatory`),
 *   and any credential of a demonstration account (`demo-account`) - a
 *   hidden button and a forged POST meet the same predicate.
 * - Linking by the address alone (QA3): allowsEmailLinking() refuses an
 *   account that has a second factor or an administration role
 *   (SecurityPolicy::isAdministrator()); PolicyLinking hands it to
 *   omniauth/oauth.
 */
class Policy implements SecondFactorPolicy
{
    public const MANDATORY = 'two-factor-mandatory';
    public const DEMO = 'demo-account';

    public function __construct(
        protected readonly SecurityPolicy $security,
        protected readonly SecondFactorPolicy $inner,
    ) {
    }

    public function required(object $account, array $passed, Context $context): array
    {
        if ($this->security->isDemoAccount($account)) {
            return [];
        }

        return $this->inner->required($account, $passed, $context);
    }

    /** Must this account set up a second factor before carrying on (signed in, sent to the enrolment)? */
    public function needsEnrolment(object $account): bool
    {
        return $this->security->needsEnrolment($account);
    }

    /** May this account be linked to an identity provider by its address alone (QA3)? */
    public function allowsEmailLinking(object $account): bool
    {
        return !$this->security->isDemoAccount($account)
            && !$this->security->isAdministrator($account)
            && !$this->security->hasSecondFactor($account);
    }

    /** Listens to omniauth's Revoking event. */
    public function onRevoking(Revoking $event): void
    {
        if (!$this->security->canChangeCredentials($event->account)) {
            $event->refuse(self::DEMO);

            return;
        }
        if ($this->security->canDisableTwoFactor($event->account)) {
            return;
        }
        // The second factor that would remain once this one is off.
        $remains = match ($event->factor) {
            Factor::Totp => method_exists($event->account, 'isEmailAuthEnabled') && $event->account->isEmailAuthEnabled(),
            Factor::EmailCode => method_exists($event->account, 'isTotpAuthenticationEnabled') && $event->account->isTotpAuthenticationEnabled(),
            default => true,
        };
        if (!$remains) {
            $event->refuse(self::MANDATORY);
        }
    }

    /** @return array<class-string, string> omniauth's events and the method of this class each calls */
    public static function events(): array
    {
        return [Revoking::class => 'onRevoking'];
    }
}
