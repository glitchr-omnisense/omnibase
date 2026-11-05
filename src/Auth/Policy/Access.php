<?php

namespace Base\Auth\Policy;

use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\Referrer;

/**
 * Who may open the site while its access is restricted (plan "omnibase agnostic", step F5e, slice 1):
 * the rule omnibridge/symfony's AccessVoter held, on the core's services, for every host.
 *
 * - ADMIN_ACCESS: `base.access_restriction.admin_access`, or a super administrator;
 * - USER_ACCESS: `user_access`, or an administrator while `admin_access` is on;
 * - ANONYMOUS_ACCESS: `public_access`, or a signed-in user while `admin_access` or `user_access` is on;
 * - EXCEPTION_ACCESS: the address is outside the restricted firewalls (`base.access_restriction.firewalls`),
 *   or matches one of `base.access_restriction.exceptions` - its environment, locale, country, language,
 *   and the regular expressions of its scheme, host, domain, subdomain and path; the exceptions of the
 *   locale (then its language, the default locale, its language, none) read once, when the policy is made.
 * MAINTENANCE_ACCESS and LAUNCH_ACCESS are Maintenance's and Launch's, which ask exception().
 *
 * The host gives what it alone knows: the firewall an address belongs to (a callable answering its
 * name, or null where it is in none: everything is allowed then), the environment's name, and the
 * current address when the subject is not one (get_url() by default). The three switches and the
 * first firewall asked are kept for the policy's life, as the voter kept them for the request's.
 */
class Access
{
    public const ANONYMOUS_ACCESS = 'ANONYMOUS_ACCESS';
    public const USER_ACCESS = 'USER_ACCESS';
    public const ADMIN_ACCESS = 'ADMIN_ACCESS';
    public const EXCEPTION_ACCESS = 'EXCEPTION_ACCESS';

    public const ATTRIBUTES = [self::EXCEPTION_ACCESS, Maintenance::ATTRIBUTE, Launch::ATTRIBUTE, self::ANONYMOUS_ACCESS, self::USER_ACCESS, self::ADMIN_ACCESS];

    /** @var list<array<string, mixed>> */
    protected array $urlExceptions;

    private ?bool $adminAccess = null;
    private ?bool $userAccess = null;
    private ?bool $publicAccess = null;
    private bool $firewallAsked = false;
    private ?string $firewall = null;

    /**
     * @param \Closure(string|Referrer): ?string $firewall the name of the firewall an address belongs to, null for none
     * @param \Closure(): string|string          $environment the environment's name
     */
    public function __construct(
        protected readonly ParameterBagInterface $parameterBag,
        protected readonly LocalizerInterface $localizer,
        private readonly \Closure $firewallOf,
        private readonly \Closure|string $environment,
    ) {
        $exceptions = $this->parameterBag->get('base.access_restriction.exceptions');
        $this->urlExceptions = array_search_by($exceptions, 'locale', $this->localizer->getLocale());
        $this->urlExceptions ??= array_search_by($exceptions, 'locale', $this->localizer->getLocaleLang());
        $this->urlExceptions ??= array_search_by($exceptions, 'locale', $this->localizer->getDefaultLocale());
        $this->urlExceptions ??= array_search_by($exceptions, 'locale', $this->localizer->getDefaultLocaleLang());
        $this->urlExceptions ??= array_search_by($exceptions, 'locale', null) ?? [];
    }

    public function supports(string $attribute): bool
    {
        return \in_array($attribute, [self::ADMIN_ACCESS, self::USER_ACCESS, self::ANONYMOUS_ACCESS, self::EXCEPTION_ACCESS], true);
    }

    /** The decision on one of ADMIN_ACCESS, USER_ACCESS, ANONYMOUS_ACCESS, EXCEPTION_ACCESS; the subject a user or an address. */
    public function decide(string $attribute, mixed $subject): bool
    {
        $this->adminAccess ??= filter_var($this->parameterBag->get('base.access_restriction.admin_access'), FILTER_VALIDATE_BOOLEAN);
        $this->userAccess ??= filter_var($this->parameterBag->get('base.access_restriction.user_access'), FILTER_VALIDATE_BOOLEAN);
        $this->publicAccess ??= filter_var($this->parameterBag->get('base.access_restriction.public_access'), FILTER_VALIDATE_BOOLEAN);

        $user = $subject instanceof \App\Entity\User ? $subject : null;

        switch ($attribute) {
            case self::ADMIN_ACCESS:
                $access = $this->adminAccess;
                $access |= $user && $user->isGranted('ROLE_SUPERADMIN');

                return (bool) $access;

            case self::USER_ACCESS:
                $access = $this->userAccess;
                $access |= $this->adminAccess && $user && $user->isGranted('ROLE_ADMIN');

                return (bool) $access;

            case self::ANONYMOUS_ACCESS:
                $access = $this->publicAccess;
                $access |= ($this->adminAccess || $this->userAccess) && $user && $user->isGranted('ROLE_USER');

                return (bool) $access;

            case self::EXCEPTION_ACCESS:
                return $this->exception($subject);
        }

        return false;
    }

    /** Whether the address (the subject when it is one, else the current one) is let through the restriction. */
    public function exception(mixed $subject): bool
    {
        $url = \is_string($subject) || $subject instanceof Referrer ? $subject : get_url();

        $firewallNames = $this->parameterBag->get('base.access_restriction.firewalls');
        $isRestrictedFirewall = false;

        if (!$this->firewallAsked) {
            $this->firewall = ($this->firewallOf)($url);
            $this->firewallAsked = null !== $this->firewall;
        }
        if (null === $this->firewall) {
            return true;
        }

        foreach ($firewallNames as $firewallName) {
            $isRestrictedFirewall |= $this->firewall == $firewallName;
        }
        if (!$isRestrictedFirewall) {
            return true;
        }

        $url = parse_url((string) $url);
        foreach ($this->urlExceptions as $urlException) {
            $exception = true;

            $environment = $urlException['env'] ?? null;
            if (null !== $environment) {
                $exception &= $environment == ($this->environment instanceof \Closure ? ($this->environment)() : $this->environment);
            }
            $locale = $urlException['locale'] ?? null;
            if (null !== $locale) {
                $exception &= $locale == $this->localizer->getLocale();
            }
            $country = $urlException['country'] ?? null;
            if (null !== $country) {
                $exception &= $country == $this->localizer->getLocaleCountry();
            }
            $lang = $urlException['lang'] ?? null;
            if (null !== $lang) {
                $exception &= $lang == $this->localizer->getLocaleLang();
            }
            foreach (['scheme', 'host', 'domain', 'subdomain', 'path'] as $part) {
                $pattern = $urlException[$part] ?? null;
                if (null !== $pattern) {
                    $exception &= \array_key_exists($part, $url) && preg_match('/'.$pattern.'/', $url[$part]);
                }
            }

            if ($exception) {
                return true;
            }
        }

        return false;
    }
}
