<?php

namespace Base\Twig\Extension;

use Base\Demo\DemoAccount;
use Base\Demo\DemoAccountFactory;
use Base\Demo\DemoAccountRegistry;
use Base\Demo\DemoMode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The demonstration in a template (docs/20-architecture/demo.md):
 *
 *     {% if demo_mode() %}...{% endif %}     the `demo` environment, and no other
 *     {% for account in demo_accounts() %}   the declared accounts a visitor may sign in as
 *
 * @Base/demo/_banner.html.twig and @Base/demo/_accounts.html.twig are what a
 * layout and a sign-in page include; both print nothing outside `demo`. The
 * banner names the first of these accounts to a visitor who is not signed in.
 */
class DemoTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly DemoMode $mode,
        private readonly DemoAccountRegistry $registry,
        private readonly DemoAccountFactory $factory,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('demo_mode', $this->mode->isActive(...)),
            new TwigFunction('demo_accounts', $this->accounts(...)),
        ];
    }

    /**
     * The declared accounts that exist and may be signed in as - none
     * outside `demo`, and never one that opens the back office
     * (base.demo.admin_roles; the super-administrator's roles reach them).
     *
     * @return DemoAccount[]
     */
    public function accounts(): array
    {
        if (!$this->mode->isActive()) {
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
