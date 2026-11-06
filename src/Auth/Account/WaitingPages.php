<?php

namespace Base\Auth\Account;

use Base\Entity\User;
use Base\Screen\Template;
use Base\Service\LauncherInterface;
use Base\Service\MaintenanceProviderInterface;
use Omnibridge\Contract\Access;

/**
 * The pages a visitor waits on - the maintenance, the launch, a page not open yet, an account waiting for its
 * approval -, written once for every host (plan "omnibase agnostic", step F5e, slice 5; formerly
 * SecurityController::Maintenance(), Launch(), Pending() and PendingForApproval()): their templates and data.
 */
final class WaitingPages
{
    public function __construct(
        private readonly Access $access,
        private readonly ?MaintenanceProviderInterface $maintenance = null,
        private readonly ?LauncherInterface $launcher = null,
    ) {
    }

    public function maintenance(): Outcome
    {
        return Outcome::page(new Template('security/maintenance.html.twig', [
            'remainingTime' => $this->maintenance?->getRemainingTime(),
            'percentage' => $this->maintenance?->getPercentage(),
            'downtime' => $this->maintenance?->getDowntime(),
            'uptime' => $this->maintenance?->getUptime(),
        ]));
    }

    public function launch(): Outcome
    {
        return Outcome::page(new Template('security/launchdate.html.twig', [
            'launchdate' => $this->launcher?->getLaunchdate(),
            'is_launchedd' => $this->launcher?->isLaunched(),
        ]));
    }

    public function pending(): Outcome
    {
        return Outcome::page(new Template('security/pending.html.twig'));
    }

    /** An account approved meanwhile goes to the site's home page (app_index). */
    public function pendingForApproval(): Outcome
    {
        $user = $this->access->getUser();
        if ($user instanceof User && $user->isApproved()) {
            return Outcome::route('app_index');
        }

        return Outcome::page(new Template('security/pendingForApproval.html.twig'));
    }
}
