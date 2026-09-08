<?php

namespace Base\Service;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The site-wide account-security policy an administrator sets, and the single
 * place that decides what a *user* is then allowed to change about their own
 * account.
 *
 * The rule the whole class exists to express: the administrator wins. A user
 * may switch two-factor authentication on whenever the feature is available,
 * but may only switch it back off while the administrator has not made it
 * mandatory. Every "can the user do X" question below is asked from the
 * settings page and from the controller that performs X, so a hidden button
 * and a hand-crafted POST are refused by the same predicate.
 *
 * Defaults matter here. A setting the administrator has never saved has no row
 * at all, and SettingBag::getScalar() returns null for it - which filter_var()
 * reads as false. Defaulting everything to false would silently switch
 * passkeys and TOTP off for every existing account the moment this class
 * shipped, so each flag below carries its own explicit default and only a real
 * stored value overrides it.
 */
class SecurityPolicy
{
    public const TWO_FACTOR = 'base.settings.security.two_factor';
    public const TWO_FACTOR_MANDATORY = 'base.settings.security.two_factor.mandatory';
    public const PASSKEYS = 'base.settings.security.passkeys';
    public const NEW_DEVICE_EMAIL = 'base.settings.security.new_device_email';
    public const NEW_DEVICE_PROMPT = 'base.settings.security.new_device_prompt';

    /**
     * Session key set by a sign-in from a browser this account has never
     * used, on an account with no second factor: the next page shows, once,
     * the optional offer to set up a one-time code. Cleared when answered.
     */
    public const SESSION_NEW_DEVICE_PROMPT = 'security_new_device_prompt';

    /** Cookie remembering that the offer above was declined - it is never repeated. */
    public const COOKIE_NEW_DEVICE_PROMPT_DISMISSED = 'base_2fa_offer';

    /** Session key holding a "not now" answer to the enrolment prompt. */
    public const SESSION_ENROLMENT_SKIPPED = 'security_2fa_enrolment_skipped';

    public function __construct(private SettingBagInterface $settingBag)
    {
    }

    /**
     * @param bool $default used when the administrator has never saved this setting
     */
    private function flag(string $path, bool $default): bool
    {
        $value = $this->settingBag->getScalar($path);
        if (null === $value || '' === $value) {
            return $default;
        }

        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** Is two-factor authentication offered to users at all? */
    public function isTwoFactorAvailable(): bool
    {
        return $this->flag(self::TWO_FACTOR, true);
    }

    /**
     * Must every user hold a second factor?
     *
     * Mandatory only means anything while the feature is available in the
     * first place - an administrator who turns two-factor off entirely and
     * leaves this checkbox ticked has not thereby locked everyone out.
     */
    public function isTwoFactorMandatory(): bool
    {
        return $this->isTwoFactorAvailable() && $this->flag(self::TWO_FACTOR_MANDATORY, false);
    }

    /** Are passkeys offered as a login method? */
    public function arePasskeysAvailable(): bool
    {
        return $this->flag(self::PASSKEYS, true);
    }

    /** Should signing in from an unrecognised browser send a confirmation email? */
    public function isNewDeviceEmailEnabled(): bool
    {
        return $this->flag(self::NEW_DEVICE_EMAIL, false);
    }

    /**
     * Does this account already hold a second factor?
     *
     * A passkey deliberately does not count. It replaces the password rather
     * than adding to it, so an account whose only protection is a passkey has
     * one factor, not two, and a site that mandates two-factor should still
     * ask for the second one.
     */
    public function hasSecondFactor(?UserInterface $user): bool
    {
        if (null === $user) {
            return false;
        }

        if (method_exists($user, 'isTotpAuthenticationEnabled') && $user->isTotpAuthenticationEnabled()) {
            return true;
        }

        return method_exists($user, 'isEmailAuthEnabled') && $user->isEmailAuthEnabled();
    }

    /**
     * May this user turn their second factor off again?
     *
     * This is the precedence rule in one line: no, while the administrator
     * requires one.
     */
    public function canDisableTwoFactor(): bool
    {
        return !$this->isTwoFactorMandatory();
    }

    /** May this user still enrol - i.e. is the feature switched on for them? */
    public function canEnableTwoFactor(): bool
    {
        return $this->isTwoFactorAvailable();
    }

    /**
     * Should the first sign-in from an unknown browser offer, once, to set up
     * a one-time code? Only meaningful while two-factor is available and not
     * already mandatory (then the enrolment prompt does the asking).
     */
    public function isNewDevicePromptEnabled(): bool
    {
        return $this->isTwoFactorAvailable() && !$this->isTwoFactorMandatory() && $this->flag(self::NEW_DEVICE_PROMPT, true);
    }

    /**
     * Should this user be asked to enrol before carrying on?
     *
     * True only while the site requires a second factor and this account has
     * none. Whether the user is then allowed to postpone the answer is
     * decided by the caller, not here - see canPostponeEnrolment().
     */
    public function needsEnrolment(?UserInterface $user): bool
    {
        return null !== $user && $this->isTwoFactorMandatory() && !$this->hasSecondFactor($user);
    }

    /**
     * May a "not now" be remembered for good?
     *
     * No, while enrolment is mandatory: the prompt offers a skip so nobody is
     * trapped mid-task, but that answer lives in the session and the prompt
     * comes back on the next sign-in. When two-factor is merely offered, a
     * dismissal can be kept for good.
     */
    public function canPostponeEnrolmentPermanently(): bool
    {
        return !$this->isTwoFactorMandatory();
    }
}
