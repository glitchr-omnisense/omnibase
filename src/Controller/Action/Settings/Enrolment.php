<?php

namespace Base\Controller\Action\Settings;

use Base\Auth\EnrolmentPrompt;
use Base\Screen\Redirect;
use Base\Screen\Template;
use Base\Service\SecurityPolicy;
use Omniauth\Contract\State;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Urls;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The one-off "your account needs a second factor" page, written once for
 * every host (step R29 of the audit "lesringostars face au master plan";
 * formerly UserSettingsController::Enrolment()).
 *
 * Reached by being sent here (EnrolmentPrompt: Symfony's
 * SecurityEnrolmentSubscriber, omnibridge/laravel's enrolment middleware),
 * while the administrator or a role requires a second factor the account
 * does not have, or once after a sign-in from an unknown browser (the
 * optional offer: other words, a "no thanks" remembered for good). It offers
 * the methods the settings page does, plus a way out where the application
 * allows a "later" (EnrolmentSkip). Nothing to ask: the settings, and the
 * offer's flag forgotten. The session is omniauth's State, the host's.
 * A visitor is sent to sign in (Symfony's firewall does it first: ^/settings is
 * for members).
 */
#[Route('/settings/security-required', name: 'user_settings_enrolment')]
final class Enrolment
{
    private readonly EnrolmentPrompt $prompt;

    public function __construct(
        private readonly Access $access,
        private readonly SecurityPolicy $policy,
        private readonly Urls $urls,
        private readonly ?State $session = null,
    ) {
        $this->prompt = new EnrolmentPrompt($policy);
    }

    public function __invoke(): Template|Redirect
    {
        $user = $this->access->getUser();
        if (null === $user) {
            return new Redirect($this->urls->url('security_login'));
        }
        $optional = $this->prompt->page($user, (bool) $this->session?->get(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT));
        if (null === $optional) {
            $this->session?->remove(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT);

            return new Redirect($this->urls->path('user_settings'));
        }

        return new Template('client/user/settings_enrolment.html.twig', [
            'user' => $user,
            'policy' => $this->policy,
            'optional' => $optional,
            // No "not now" where the application allows none (base.security.two_factor.postpone: false).
            'skippable' => $optional || $this->policy->canSkipEnrolment($user),
        ]);
    }
}
