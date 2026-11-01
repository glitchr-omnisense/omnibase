<?php

namespace Base\Twig\Extension;

use Base\Demo\DemoAccount;
use Base\Demo\DemoAccountFactory;
use Base\Demo\DemoAccountRegistry;
use Base\Demo\DemoMode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Base\View\Demo;

/**
 * The demonstration in a template (docs/20-architecture/demo.md):
 *
 *     {% if demo_mode() %}...{% endif %}     the `demo` environment, and no other
 *     {% for account in demo_accounts() %}   the declared accounts a visitor may sign in as
 *
 * @Base/demo/_banner.html.twig and @Base/demo/_accounts.html.twig are what a
 * layout and a sign-in page include; both print nothing outside `demo`. The
 * banner names the first of these accounts to a visitor who is not signed in.
 *
 * Framework-free: a host that runs no demonstration (Laravel: no `demo`
 * kernel environment) gives the mode alone - never active, no account.
 */
class DemoTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly DemoMode $mode,
        private readonly ?DemoAccountRegistry $registry = null,
        private readonly ?DemoAccountFactory $factory = null,
    ) {
        $this->demo = new Demo($mode, $registry, $factory);
    }

    private readonly Demo $demo;

    public function getFunctions(): array
    {
        return [
            new TwigFunction('demo_mode', $this->demo->active(...)),
            new TwigFunction('demo_accounts', $this->accounts(...)),
        ];
    }

    /**
     * The declared accounts a visitor may sign in as (Base\View\Demo).
     *
     * @return DemoAccount[]
     */
    public function accounts(): array
    {
        return $this->demo->accounts();
    }
}
