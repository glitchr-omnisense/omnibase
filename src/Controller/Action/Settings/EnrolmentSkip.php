<?php

namespace Base\Controller\Action\Settings;

use Base\Screen\Redirect;
use Base\Service\SecurityPolicy;
use Omniauth\Contract\State;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The enrolment page's "not now", written once for every host (step R29 of
 * the audit "lesringostars face au master plan"; formerly
 * UserSettingsController::EnrolmentSkip()). Its form's token (intent
 * "2fa_enrolment_skip", the field _token) is checked through the host's
 * Tokens: without it, 403. Where the application allows no "later", back to
 * the enrolment page (a hand-made POST gets the same answer as the missing
 * button). Otherwise the session says so (SESSION_ENROLMENT_SKIPPED) and the
 * offer's flag goes; after the offer, the profile and, for a year, a cookie
 * of this browser (SecurityPolicy::COOKIE_NEW_DEVICE_PROMPT_DISMISSED: the
 * only memory there is without a column of the account); after the
 * mandatory enrolment, the settings. The account is told
 * ("@notifications.settings.enrolmentSkipped", info: Notice).
 * A visitor is sent to sign in (Symfony's firewall does it first: ^/settings is
 * for members).
 */
#[Route('/settings/security-required/skip', name: 'user_settings_enrolment_skip', methods: ['POST'])]
final class EnrolmentSkip
{
    public const TOKEN = '2fa_enrolment_skip';
    public const NOTICE = '@notifications.settings.enrolmentSkipped';

    /**
     * @param (\Closure(string $label, string $message): void)|null $notice how the host tells the account (a flash)
     */
    public function __construct(
        private readonly Access $access,
        private readonly Tokens $tokens,
        private readonly SecurityPolicy $policy,
        private readonly Urls $urls,
        private readonly ?State $session = null,
        private readonly ?\Closure $notice = null,
    ) {
    }

    /** @param array<string, mixed> $body */
    public function __invoke(array $body = []): Redirect
    {
        if (null === $this->access->getUser()) {
            return new Redirect($this->urls->url('security_login'));
        }
        if (!$this->tokens->isValid(self::TOKEN, \is_string($body['_token'] ?? null) ? $body['_token'] : null)) {
            throw new AccessDeniedHttpException('The form\'s token is not valid.');
        }

        $user = $this->access->getUser();
        $offered = (bool) $this->session?->get(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT);
        if (!$this->policy->canSkipEnrolment($user)) {
            return new Redirect($this->urls->path('user_settings_enrolment'));
        }
        $this->session?->remove(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT);
        $this->session?->set(SecurityPolicy::SESSION_ENROLMENT_SKIPPED, true);

        Notice::send($this->notice, 'info', self::NOTICE);

        if (!$offered) {
            return new Redirect($this->urls->path('user_settings'));
        }

        // Declined once = not asked again from this browser for a year.
        $cookie = Cookie::create(SecurityPolicy::COOKIE_NEW_DEVICE_PROMPT_DISMISSED, '1', new \DateTimeImmutable('+1 year'), '/', null, null, true, false, Cookie::SAMESITE_LAX);

        return new Redirect($this->urls->path('user_profile'), headers: ['Set-Cookie' => (string) $cookie]);
    }
}
