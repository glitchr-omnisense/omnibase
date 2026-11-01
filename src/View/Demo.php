<?php

namespace Base\View;

use Base\Demo\DemoAccount;
use Base\Demo\DemoAccountFactory;
use Base\Demo\DemoAccountRegistry;
use Base\Demo\DemoMode;

/**
 * The demonstration in a page (plan "omnibase agnostic", step F4; docs/20-architecture/demo.md):
 * whether it runs (the `demo` environment, and no other) and the declared accounts a visitor may
 * sign in as - DemoTwigExtension's demo_mode() and demo_accounts() under Twig, the same code for
 * Blade and a React page's props. A host that runs no demonstration gives the mode alone - never
 * active, no account.
 */
final class Demo
{
    public function __construct(
        private readonly DemoMode $mode,
        private readonly ?DemoAccountRegistry $registry = null,
        private readonly ?DemoAccountFactory $factory = null,
    ) {
    }

    public function active(): bool
    {
        return $this->mode->isActive();
    }

    /**
     * The declared accounts that exist and may be signed in as - none outside `demo`, and never
     * one that opens the back office (base.demo.admin_roles; the super-administrator's roles reach
     * them).
     *
     * @return DemoAccount[]
     */
    public function accounts(): array
    {
        if (!$this->mode->isActive() || null === $this->registry || null === $this->factory) {
            return [];
        }

        $accounts = [];
        foreach ($this->registry->offered() as $identifier => $account) {
            try {
                $user = $this->factory->find($identifier);
            } catch (\Throwable) {
                return []; // no database yet: the page still answers
            }

            // Declared as a plain account, promoted since (in the back office): not offered either.
            if ($user && !$this->registry->isSuperAdmin($user) && !$this->registry->isAdministrator($user)) {
                $accounts[] = $account;
            }
        }

        return $accounts;
    }
}
