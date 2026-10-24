<?php

namespace Base\Auth;

use Base\Service\SecurityPolicy;

/**
 * When a signed-in visitor is sent to the second factor's enrolment page
 * (user_settings_enrolment), framework-free (step R29 of the audit
 * "lesringostars face au master plan"): what Base\Subscriber\
 * SecurityEnrolmentSubscriber asks under Symfony and omnibridge/laravel's
 * enrolment middleware asks under Laravel, each with its own request and
 * session.
 *
 * - Only a plain top-level GET of an HTML page is interrupted (interrupts()):
 *   a form posted lands where it was going, a script gets its JSON, a file
 *   downloads; never the pages that answer the prompt (the settings, the
 *   sign-in and sign-out, the registration, the password's, an identity
 *   provider's return, the profiler's).
 * - The offer after a sign-in from an unknown browser (the session's
 *   SecurityPolicy::SESSION_NEW_DEVICE_PROMPT, set by the sign-in's
 *   WarnNewDevice) is made once, while the account has no second factor;
 *   the flag is forgotten otherwise (forgetsTheOffer()).
 * - A second factor the account must have and does not (SecurityPolicy::
 *   needsEnrolment()): every page, unless the session said "not now" where
 *   the application allows a "later", or the visitor is someone impersonating
 *   the account.
 */
final class EnrolmentPrompt
{
    /**
     * Path prefixes the prompt never interrupts: the settings pages hold the
     * answer, the security paths the way in and out, /_ the profiler.
     */
    public const EXEMPT_PREFIXES = [
        '/settings',
        '/login',
        '/logout',
        '/register',
        '/reset-password',
        '/connect/',
        '/_',
    ];

    public function __construct(private readonly SecurityPolicy $policy)
    {
    }

    /**
     * Whether a request asks for a page the prompt may stand in for.
     *
     * @param list<string> $acceptable the content types the client accepts, in order (none: anything)
     */
    public static function interrupts(string $method, bool $script, array $acceptable, string $path): bool
    {
        if ('GET' !== strtoupper($method) || $script) {
            return false;
        }
        // Anything that did not ask for a page - an image, a JSON endpoint, a Turbo frame - gets no
        // redirect it could not render anyway.
        if ([] !== $acceptable && !\in_array('text/html', $acceptable, true) && !\in_array('*/*', $acceptable, true)) {
            return false;
        }
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the visitor goes to the enrolment page now.
     *
     * @param bool $offered       the session holds the offer after a new device
     * @param bool $skipped       the session said "not now" (SecurityPolicy::SESSION_ENROLMENT_SKIPPED)
     * @param bool $impersonating the visitor impersonates the account (a super-admin's support)
     */
    public function due(?object $user, bool $offered, bool $skipped, bool $impersonating): bool
    {
        if ($offered && null !== $user && !$this->policy->hasSecondFactor($user)) {
            return true;
        }
        if (!$this->policy->needsEnrolment($user)) {
            return false;
        }
        // "Not now" lasts the session - unless the application offers no "later" to the accounts a
        // second factor is required of (base.security.two_factor.postpone: false).
        if ($skipped && $this->policy->canSkipEnrolment($user)) {
            return false;
        }

        return !$impersonating;
    }

    /** Whether the offer after a new device is moot (nobody signed in, or a second factor set up since): its flag goes. */
    public function forgetsTheOffer(?object $user, bool $offered): bool
    {
        return $offered && (null === $user || $this->policy->hasSecondFactor($user));
    }

    /**
     * What the enrolment page shows: null when there is nothing to ask (the page sends to the
     * settings, and the offer's flag goes), else whether it is the optional offer after a new
     * device (other words, a "no thanks" remembered for good) rather than the mandatory enrolment.
     */
    public function page(?object $user, bool $offered): ?bool
    {
        if ($this->policy->needsEnrolment($user)) {
            return false;
        }

        return null !== $user && $offered && !$this->policy->hasSecondFactor($user) ? true : null;
    }
}
