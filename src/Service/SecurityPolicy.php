<?php

namespace Base\Service;

use Base\Demo\DemoAccountRegistry;
use Base\Demo\DemoMode;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

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
 *
 * Besides "everyone or no one" (the administrator's setting), the application
 * may require a second factor of some roles only
 * (base.security.two_factor.required_roles: [ROLE_STAFF]): whoever holds one
 * of them, directly or through the role hierarchy, is asked to enrol and
 * cannot switch the factor back off; everyone else stays free to choose.
 * Hence the optional $user of the questions below: without one they answer
 * for the whole site, with one for that account.
 *
 * The accounts are asked of as plain objects (omnibase's users), not as
 * Security's UserInterface: omniauth's Base\Auth\Policy asks the same
 * questions under any host (plan "omniauth", AU4, the former P4). The role
 * hierarchy is Symfony's where there is one, else the account's own roles.
 *
 * In the `demo` environment a demonstration account (Base\Demo) is shared by
 * every visitor: nothing is required of it - not the second factor of its
 * role, not the administrator's "mandatory" - and it may change none of its
 * credentials (isDemoAccount(), canChangeCredentials()).
 */
class SecurityPolicy
{
    public const TWO_FACTOR = 'base.settings.security.two_factor';
    public const TWO_FACTOR_MANDATORY = 'base.settings.security.two_factor.mandatory';
    public const PASSKEYS = 'base.settings.security.passkeys';
    public const NEW_DEVICE_EMAIL = 'base.settings.security.new_device_email';
    public const NEW_DEVICE_PROMPT = 'base.settings.security.new_device_prompt';

    /** The roles of the administration: an account holding one is never linked by its address (isAdministrator()). */
    public const ADMINISTRATION_ROLES = ['ROLE_ADMIN', 'ROLE_SUPERADMIN'];

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

    /**
     * @param string[] $requiredRoles roles whose holders must have a second factor (base.security.two_factor.required_roles)
     * @param bool     $postpone      whether the enrolment prompt offers a "not now" (base.security.two_factor.postpone)
     * @param (\Closure(string[]): string[])|null $reachableRoles the roles some roles reach, where the host has no Symfony hierarchy (omnibridge/laravel: config/omniauth.php's role_hierarchy)
     */
    public function __construct(
        private SettingBagInterface $settingBag,
        private ?RoleHierarchyInterface $roleHierarchy = null,
        #[Autowire('%base.security.two_factor.required_roles%')] private array $requiredRoles = [],
        #[Autowire('%base.security.two_factor.postpone%')] private bool $postpone = true,
        private ?DemoMode $demoMode = null,
        private ?DemoAccountRegistry $demoAccounts = null,
        private ?\Closure $reachableRoles = null,
    ) {
    }

    /**
     * Is this one of the declared demonstration accounts, in the `demo`
     * environment? Never true anywhere else: in dev and test the same
     * accounts are ordinary ones.
     */
    public function isDemoAccount(?object $user): bool
    {
        return null !== $user && $this->demoMode?->isActive() && null !== $this->demoAccounts?->of($user);
    }

    /**
     * May this account change its password, its address, its second factor,
     * or close itself? Not a demonstration account: every visitor signs in
     * with it, and whoever changed one of these would lock the next out.
     */
    public function canChangeCredentials(?object $user): bool
    {
        return !$this->isDemoAccount($user);
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
     * Must every user hold a second factor - or, given an account, must this one?
     *
     * Mandatory only means anything while the feature is available in the
     * first place - an administrator who turns two-factor off entirely and
     * leaves this checkbox ticked has not thereby locked everyone out.
     */
    public function isTwoFactorMandatory(?object $user = null): bool
    {
        if (!$this->isTwoFactorAvailable() || $this->isDemoAccount($user)) {
            return false;
        }

        return $this->flag(self::TWO_FACTOR_MANDATORY, false) || $this->isTwoFactorRequiredByRole($user);
    }

    /** @return string[] the roles whose holders must have a second factor */
    public function getRequiredRoles(): array
    {
        return $this->requiredRoles;
    }

    /**
     * Does this account hold one of the roles a second factor is required of
     * (base.security.two_factor.required_roles), directly or through the role
     * hierarchy? Asked of the account itself, not of the current token: the
     * settings page and the enrolment prompt may be asked about any user.
     */
    public function isTwoFactorRequiredByRole(?object $user): bool
    {
        if (null === $user || [] === $this->requiredRoles || !$this->isTwoFactorAvailable() || $this->isDemoAccount($user)) {
            return false;
        }

        return [] !== array_intersect($this->requiredRoles, $this->roles($user));
    }

    /**
     * Does this account hold an administration role (ROLE_ADMIN,
     * ROLE_SUPERADMIN, directly or through the role hierarchy) or one a second
     * factor is required of? Such an account is never linked to an identity
     * provider by its address alone (plan "omniauth", Marco's decision QA3,
     * Base\Auth\Policy): whoever holds the provider's account would hold the
     * administration's.
     */
    public function isAdministrator(?object $user): bool
    {
        if (null === $user || !method_exists($user, 'getRoles')) {
            return false;
        }

        return [] !== array_intersect(self::ADMINISTRATION_ROLES, $this->roles($user)) || $this->isTwoFactorRequiredByRole($user);
    }

    /**
     * The account's roles, those the role hierarchy reaches from them included
     * when there is one (Symfony's; a host without one gives the account's own).
     *
     * @return string[]
     */
    private function roles(object $user): array
    {
        $roles = array_map(static fn ($role) => $role instanceof \BackedEnum ? (string) $role->value : (string) $role, $user->getRoles());

        if (null === $this->roleHierarchy && null !== $this->reachableRoles) {
            return ($this->reachableRoles)($roles);
        }

        return $this->roleHierarchy ? $this->roleHierarchy->getReachableRoleNames($roles) : $roles;
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
    public function hasSecondFactor(?object $user): bool
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
    public function canDisableTwoFactor(?object $user = null): bool
    {
        return !$this->isTwoFactorMandatory($user);
    }

    /** May this user still enrol - i.e. is the feature switched on for them? (Never a demonstration account.) */
    public function canEnableTwoFactor(?object $user = null): bool
    {
        return $this->isTwoFactorAvailable() && !$this->isDemoAccount($user);
    }

    /**
     * Should the first sign-in from an unknown browser offer, once, to set up
     * a one-time code? Only meaningful while two-factor is available and not
     * already mandatory (then the enrolment prompt does the asking).
     *
     * OFF unless an administrator turns it on. It interrupts a sign-in the
     * person did not ask to have interrupted, which is friction a public site
     * should choose deliberately rather than inherit from a default.
     */
    public function isNewDevicePromptEnabled(): bool
    {
        return $this->isTwoFactorAvailable() && !$this->isTwoFactorMandatory() && $this->flag(self::NEW_DEVICE_PROMPT, false);
    }

    /**
     * Should this user be asked to enrol before carrying on?
     *
     * True only while the site requires a second factor and this account has
     * none. Whether the user is then allowed to postpone the answer is
     * decided by the caller, not here - see canPostponeEnrolment().
     */
    public function needsEnrolment(?object $user): bool
    {
        return null !== $user && $this->isTwoFactorMandatory($user) && !$this->hasSecondFactor($user);
    }

    /**
     * Does the enrolment prompt offer this account a "not now"?
     *
     * Yes by default: the skip exists so that a policy change strands nobody
     * mid-task, and it only lasts the session. An application that wants no
     * "later" for the accounts a second factor is required of (staff reading
     * patients' files) sets base.security.two_factor.postpone: false - the
     * prompt then comes back on every page until the account has one.
     */
    public function canSkipEnrolment(?object $user = null): bool
    {
        return $this->postpone || !$this->needsEnrolment($user);
    }

    /**
     * May a "not now" be remembered for good?
     *
     * No, while enrolment is mandatory: the prompt offers a skip so nobody is
     * trapped mid-task, but that answer lives in the session and the prompt
     * comes back on the next sign-in. When two-factor is merely offered, a
     * dismissal can be kept for good.
     */
    public function canPostponeEnrolmentPermanently(?object $user = null): bool
    {
        return !$this->isTwoFactorMandatory($user);
    }
}
