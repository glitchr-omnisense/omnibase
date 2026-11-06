<?php

namespace Base\Request\Rule;

use Base\Auth\Policy\Launch;
use Base\Request\Decision;
use Base\Request\Page;
use Base\Service\LauncherInterface;
use Base\Service\Model\IntlDateTime;

/**
 * The launch page (plan "omnibase agnostic", step F5e, slice 2; formerly Launcher::redirectOnDeny(), which
 * asks it now, under omnibridge/symfony's SecuritySubscriber). Where the site waits for its launch date and
 * sends the visitors to it (`base.settings.launchdate.redirect_on_deny`), outside the widgets', the
 * profiler's, the back office's and the account's pages: before the date, an editor is told when and goes
 * on; the launch page itself and a visitor granted LAUNCH_ACCESS (Base\Auth\Policy\Launch) go on; anyone
 * else is signed out and sent to the launch page, the profiler off. Once launched, the launch page sends to
 * the site's index.
 */
final class LaunchPage
{
    public const ROUTE = 'security_launch';

    public function __construct(private readonly LauncherInterface $launcher, private readonly bool $redirectOnDeny = false, private readonly ?string $index = null)
    {
    }

    /** @param \Closure(string): bool $granted the host's decision on an attribute or a role, for the visitor */
    public function decide(Page $page, \Closure $granted, ?string $locale = null): Decision
    {
        if (!$this->redirectOnDeny || !($date = $this->launcher->getLaunchdate())) {
            return Decision::continue();
        }
        if ($page->isUx() || $page->isProfiler() || $page->isAdmin() || $page->isSecured()) {
            return Decision::continue();
        }
        if ($this->launcher->isLaunched()) {
            return null !== $this->index && self::ROUTE === $page->name() ? Decision::redirect($this->index) : Decision::continue();
        }
        if ($granted('ROLE_EDITOR')) {
            $at = IntlDateTime::createFromDateTime($date, $locale);

            return Decision::continue()->withNotice('launcher.banner', [$at->format('dd MMMM YYYY'), $at->format('HH:mm')], 'warning');
        }
        if (self::ROUTE === $page->name() || $granted(Launch::ATTRIBUTE)) {
            return Decision::continue();
        }

        return Decision::redirect(self::ROUTE)->withSignOut()->withoutProfiler();
    }
}
