<?php

namespace Base\Auth;

use Base\Demo\DemoAccountRegistry;
use Omniauth\Contract\Passwords;

/**
 * The passwords in a demonstration (the `demo` environment, plan "omniauth"
 * AU7): Base\Subscriber\DemoBackOfficeSubscriber's rule where omniauth checks
 * the password - the super-administrator signs in with
 * base.demo.superadmin_password, an account that opens the back office with
 * base.demo.admin_password, never with the password the database holds (a
 * copy of the production's); while a tier's secret is not set, its accounts
 * have no password at all. Every other account's is its own (the store this
 * decorates). A secret is never written in place of a password.
 */
class DemoPasswords implements Passwords
{
    /** @var array<string, string> a secret => its hash, drawn once */
    private array $hashes = [];

    public function __construct(
        protected readonly Passwords $inner,
        protected readonly DemoAccountRegistry $accounts,
        #[\SensitiveParameter] protected readonly ?string $superAdminSecret = null,
        #[\SensitiveParameter] protected readonly ?string $adminSecret = null,
    ) {
    }

    public function hash(object $account): ?string
    {
        $tier = $this->tier($account);
        if (false === $tier) {
            return $this->inner->hash($account);
        }
        if (null === $tier || '' === $tier) {
            return null;
        }

        // bcrypt at cost 13, Symfony's "auto": the hasher checks it as it checks any password, and never rehashes it.
        return $this->hashes[$tier] ??= password_hash($tier, \PASSWORD_BCRYPT, ['cost' => 13]);
    }

    public function update(object $account, string $hash): void
    {
        if (false === $this->tier($account)) {
            $this->inner->update($account, $hash);
        }
    }

    /** The account's secret (null: not set), or false when it is not one of the back office. */
    private function tier(object $account): string|null|false
    {
        return match (true) {
            $this->accounts->isSuperAdmin($account) => $this->superAdminSecret,
            $this->accounts->isAdministrator($account) => $this->adminSecret,
            default => false,
        };
    }
}
