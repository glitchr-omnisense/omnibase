<?php

namespace Base\Demo;

use Base\Auth\Account\Outcome;
use App\Entity\User\Notification;
use Omnibridge\Contract\Tokens;

/**
 * Signing in as a demonstration account in one click, written once for every host (plan "omnibase agnostic",
 * step F5e, slice 5; formerly DemoController::Login()): the `demo` environment only, a POST with its token
 * (never a link a prefetch would follow), a declared account only, and never one that opens the back office
 * (base.demo.admin_roles) or reaches ROLE_SUPERADMIN - declared so or promoted since: those sign in with their
 * secret. A refusal is said and leads back to the sign-in page.
 */
final class DemoSignIn
{
    public const TOKEN = 'demo_login';
    public const LOGIN_ROUTE = 'security_login';

    public function __construct(
        private readonly DemoMode $demo,
        private readonly DemoAccountRegistry $accounts,
        private readonly Tokens $tokens,
    ) {
    }

    public function isActive(): bool
    {
        return $this->demo->isActive();
    }

    /**
     * The account to sign in (the host then answers what its sign-in answers, else the index), or the refusal.
     *
     * @param \Closure(string): ?object $load the host's account by its identifier, null for none
     */
    public function decide(?string $token, ?string $account, \Closure $load): Outcome
    {
        if (!$this->demo->isActive()) {
            return Outcome::missing();
        }
        if (!$this->tokens->isValid(self::TOKEN, (string) $token)) {
            return self::refuse('@notifications.demo.expired');
        }

        $declared = $this->accounts->get((string) $account);
        $user = null !== $declared ? $load($declared->identifier) : null;
        if (null === $declared || null === $user) {
            return self::refuse('@notifications.demo.unknown');
        }
        if ($this->accounts->isSuperAdmin($user) || $this->accounts->isAdministrator($user) || $this->accounts->reachesAdministration($declared->getAllRoles())) {
            return self::refuse('@notifications.demo.unknown');
        }

        return Outcome::signedIn($user);
    }

    private static function refuse(string $message): Outcome
    {
        (new Notification($message))->send('warning');

        return Outcome::route(self::LOGIN_ROUTE);
    }
}
