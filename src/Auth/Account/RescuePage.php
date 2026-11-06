<?php

namespace Base\Auth\Account;

use Base\Service\ReferrerInterface;
use Base\Service\SettingBagInterface;
use Omnibridge\Contract\Access;

/**
 * The back office's sign-in page ("rescue"), written once for every host (plan "omnibase agnostic", step F5e,
 * slice 5; formerly RescueController::LoginRescue()): an account signed in fully goes where it was going (else
 * the back office's home); the page shows the back office's logo (else the site's) and sends the visitor, once
 * signed in, to the page it asked for when it is one of this site's - as a path, which the sign-in matches
 * against the routes -, else to the back office's home. Which template and the back office's address are the host's.
 */
final class RescuePage
{
    /** The admin extension's page, preferred; the page bundled with the core where it is not installed. */
    public const ADMIN_TEMPLATE = '@Admin/page/login.html.twig';
    public const TEMPLATE = '@Base/security/rescue.html.twig';
    public const ADMIN_ROUTE = 'admin';

    public function __construct(
        private readonly Access $access,
        private readonly ReferrerInterface $referrer,
        private readonly ?SettingBagInterface $settings = null,
    ) {
    }

    public function signedIn(string $adminUrl): ?Outcome
    {
        if (!$this->access->isGranted('IS_AUTHENTICATED_FULLY')) {
            return null;
        }

        $target = $this->referrer->getUrl() ?? $adminUrl;
        $this->referrer->clear();

        return Outcome::url($target);
    }

    public function logo(): mixed
    {
        return $this->settings?->get('base.settings.logo.admin')['_self'] ?? $this->settings?->get('base.settings.logo')['_self'] ?? null;
    }

    /** The page asked for before the sign-in, as a path, when it is one of this site's; else the back office's home. */
    public function target(string $adminUrl): string
    {
        $url = $this->referrer->getUrl();
        if (!$url || !$this->referrer->sameSite()) {
            return $adminUrl;
        }

        $parts = parse_url($url);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return '' !== trim($path, '/') ? $path : $adminUrl;
    }
}
