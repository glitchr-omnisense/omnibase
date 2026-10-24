<?php

namespace Base\Controller\Action\Settings;

use Base\Repository\Users;
use Base\Screen\Redirect;
use Base\Service\SecurityPolicy;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The e-mailed code as a second factor, turned on or off, written once for
 * every host (step R29 of the audit "lesringostars face au master plan";
 * formerly UserSettingsController::TwoFactorAuthentication_ToggleEmail()):
 * the method the enrolment page (Enrolment) and the settings offer with a
 * button. A demonstration account changes nothing; its form's token (intent
 * "2fa_email", the field _token) is checked through the host's Tokens, 403
 * without it; turned on only where the administrator allows a second factor,
 * off only when it is not the last one a required second factor leaves. The
 * account told (Notice), then the settings.
 * A visitor is sent to sign in (Symfony's firewall does it first: ^/settings is
 * for members).
 */
#[Route('/settings/2fa/email', name: 'user_settings_2fa_email_toggle', methods: ['POST'])]
final class EmailCodeToggle
{
    public const TOKEN = '2fa_email';

    /**
     * @param (\Closure(string $label, string $message): void)|null $notice how the host tells the account (Notice)
     */
    public function __construct(
        private readonly Access $access,
        private readonly Tokens $tokens,
        private readonly SecurityPolicy $policy,
        private readonly Urls $urls,
        private readonly Users $users,
        private readonly ?\Closure $notice = null,
    ) {
    }

    /** @param array<string, mixed> $body */
    public function __invoke(array $body = []): Redirect
    {
        $settings = new Redirect($this->urls->path('user_settings'));
        $user = $this->access->getUser();
        if (null === $user) {
            return new Redirect($this->urls->url('security_login'));
        }
        if ($this->policy->isDemoAccount($user)) {
            Notice::send($this->notice, 'warning', '@notifications.demo.locked');

            return $settings;
        }
        if (!$this->tokens->isValid(self::TOKEN, \is_string($body['_token'] ?? null) ? $body['_token'] : null) || !method_exists($user, 'isEmailAuthEnabled') || !method_exists($user, 'setEmailAuthEnabled')) {
            throw new AccessDeniedHttpException('The form\'s token is not valid.');
        }

        $enabling = !$user->isEmailAuthEnabled();
        if ($enabling && !$this->policy->canEnableTwoFactor()) {
            Notice::send($this->notice, 'warning', '@notifications.settings.twoFactorUnavailable');

            return $settings;
        }
        // Turning e-mailed codes off is only a problem when they are the second factor the site insists on - when
        // nothing else would be left.
        if (!$enabling && !$this->policy->canDisableTwoFactor($user) && !(method_exists($user, 'isTotpAuthenticationEnabled') && $user->isTotpAuthenticationEnabled())) {
            Notice::send($this->notice, 'warning', '@notifications.settings.twoFactorMandatory');

            return $settings;
        }

        $user->setEmailAuthEnabled($enabling);
        $this->users->flush();
        Notice::send($this->notice, 'success', $enabling ? '@notifications.settings.emailCodeEnabled' : '@notifications.settings.emailCodeDisabled');

        return $settings;
    }
}
