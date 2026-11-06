<?php

namespace Base\Request\Rule;

use Base\Auth\Policy\Maintenance;
use Base\Request\Decision;
use Base\Request\Page;
use Base\Service\MaintenanceProviderInterface;

/**
 * The maintenance page (plan "omnibase agnostic", step F5e, slice 2; formerly MaintenanceProvider::
 * redirectOnDeny(), which asks it now, under omnibridge/symfony's SecuritySubscriber). While the site is under
 * maintenance: an editor is told so and goes on; the maintenance page itself is served as it is, nothing else
 * asked; a visitor granted MAINTENANCE_ACCESS (Base\Auth\Policy\Maintenance) goes on; anyone else is signed
 * out and sent to the maintenance page (`base.site.maintenance.redirect_on_deny`, `security_maintenance` by
 * default), the profiler off. Once it is over, the maintenance page sends to the site's index.
 */
final class MaintenancePage
{
    public const ROUTE = 'security_maintenance';

    public function __construct(private readonly MaintenanceProviderInterface $maintenance, private readonly ?string $route = null, private readonly ?string $index = null)
    {
    }

    /** @param \Closure(string): bool $granted the host's decision on an attribute or a role, for the visitor */
    public function decide(Page $page, \Closure $granted): Decision
    {
        $route = $this->route ?? self::ROUTE;
        if (!$this->maintenance->isUnderMaintenance()) {
            return null !== $this->index && $route === $page->name() ? Decision::redirect($this->index) : Decision::continue();
        }
        if ($granted('ROLE_EDITOR')) {
            return Decision::continue()->withNotice('maintenance.banner', [], 'warning');
        }
        if ($route === $page->name()) {
            return Decision::final()->withoutProfiler();
        }
        if ($granted(Maintenance::ATTRIBUTE)) {
            return Decision::continue();
        }

        return Decision::redirect($route)->withSignOut()->withoutProfiler();
    }
}
