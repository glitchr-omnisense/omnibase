<?php

namespace Base\Security\Voter;

use App\Entity\User;
use Base\Service\LocalizerInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\launcherInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\Referrer;
use Base\Service\SettingBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;

class AccessVoter extends Voter
{
    public const      LAUNCH_ACCESS = "LAUNCH_ACCESS";
    public const MAINTENANCE_ACCESS = "MAINTENANCE_ACCESS";
    public const   EXCEPTION_ACCESS = "EXCEPTION_ACCESS";

    public const ANONYMOUS_ACCESS = "ANONYMOUS_ACCESS";
    public const      USER_ACCESS = "USER_ACCESS";
    public const     ADMIN_ACCESS = "ADMIN_ACCESS";

    /**
     * @var RequestStack
     * */
    protected $requestStack;
    /**
     * @var Router
     * */
    protected $router;
    /**
     * @var ParameterBag
     * */
    protected $parameterBag;
    /**
     * @var Localizer
     * */
    protected $localizer;
    /**
     * @var SettingBag
     * */
    protected $settingBag;
    /**
     * @var FirewallMapInterface
     * */
    protected $firewallMap;
    /**
     * @var MaintenanceProvider
     * */
    protected $maintenanceProvider;
    /**
     * @var Launcher
     * */
    protected $launcher;

    protected ?array $urlExceptions;
    public function __construct(RequestStack $requestStack, RouterInterface $router, SettingBagInterface $settingBag, ParameterBagInterface $parameterBag, FirewallMapInterface $firewallMap, LocalizerInterface $localizer, MaintenanceProviderInterface $maintenanceProvider, LauncherInterface $launcher)
    {
        $this->requestStack   = $requestStack;
        $this->router         = $router;
        $this->settingBag     = $settingBag;
        $this->parameterBag   = $parameterBag;
        $this->firewallMap    = $firewallMap;
        $this->localizer = $localizer;
        $this->maintenanceProvider = $maintenanceProvider;
        $this->launcher    = $launcher;

        $this->urlExceptions   = array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", $this->localizer->getLocale());
        $this->urlExceptions ??= array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", $this->localizer->getLocaleLang());
        $this->urlExceptions ??= array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", $this->localizer->getDefaultLocale());
        $this->urlExceptions ??= array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", $this->localizer->getDefaultLocaleLang());
        $this->urlExceptions ??= array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", null) ?? [];
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EXCEPTION_ACCESS, self::MAINTENANCE_ACCESS, self::LAUNCH_ACCESS, self::ANONYMOUS_ACCESS, self::USER_ACCESS, self::ADMIN_ACCESS]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user    = $subject instanceof User ? $subject : null;
        $url     = is_string($subject) || $subject instanceof Referrer ? $subject : get_url();

        switch($attribute) {
            case self::ADMIN_ACCESS:
                $access  = filter_var($this->parameterBag->get("base.access_restriction.admin_access"), FILTER_VALIDATE_BOOLEAN);
                $access |= $user && $user->isGranted("ROLE_SUPERADMIN");
                return $access;

            case self::USER_ACCESS:
                $access  = filter_var($this->parameterBag->get("base.access_restriction.user_access"), FILTER_VALIDATE_BOOLEAN);
                $access |=
                    filter_var($this->parameterBag->get("base.access_restriction.admin_access"), FILTER_VALIDATE_BOOLEAN)
                    && $user && $user->isGranted("ROLE_ADMIN");
                return $access;

            case self::ANONYMOUS_ACCESS:
                $access  = filter_var($this->parameterBag->get("base.access_restriction.public_access"), FILTER_VALIDATE_BOOLEAN);
                $access |= (
                    filter_var($this->parameterBag->get("base.access_restriction.admin_access"), FILTER_VALIDATE_BOOLEAN) ||
                    filter_var($this->parameterBag->get("base.access_restriction.user_access"), FILTER_VALIDATE_BOOLEAN)
                ) && $user && $user->isGranted("ROLE_USER");

                return $access;

            case self::MAINTENANCE_ACCESS:
                return !$this->maintenanceProvider->isUnderMaintenance() || $this->voteOnAttribute(self::EXCEPTION_ACCESS, $subject, $token);

            case self::LAUNCH_ACCESS:
                return $this->launcher->isLaunched() || $this->voteOnAttribute(self::EXCEPTION_ACCESS, $subject, $token);

            case self::EXCEPTION_ACCESS:

                // Check if firewall is subjected to restriction
                $firewallNames = $this->parameterBag->get("base.access_restriction.firewalls");
                $isRestrictedFirewall = false;

                $firewall = $this->router->getRouteFirewall($url);
                if ($firewall == null) {
                    return true;
                }

                foreach ($firewallNames as $firewallName) {
                    $isRestrictedFirewall |= $firewall->getName() == $firewallName;
                }

                if (!$isRestrictedFirewall) {
                    return true;
                }

                $url = parse_url($url);
                foreach ($this->urlExceptions as $urlException) {
                    $exception = true;

                    $environment = $urlException["env"] ?? null;
                    if ($environment !== null) {
                        $exception &= $environment == $this->router->getEnvironment();
                    }

                    $locale = $urlException["locale"] ?? null;
                    if ($locale !== null) {
                        $exception &= $locale == $this->localizer->getLocale() ;
                    }

                    $country = $urlException["country"] ?? null;
                    if ($country !== null) {
                        $exception &= $country == $this->localizer->getLocaleCountry() ;
                    }

                    $lang = $urlException["lang"] ?? null;
                    if ($lang !== null) {
                        $exception &= $lang == $this->localizer->getLocaleLang() ;
                    }

                    $scheme = $urlException["scheme"] ?? null;
                    if ($scheme !== null) {
                        $exception &= array_key_exists("scheme", $url) && preg_match("/".$scheme."/", $url["scheme"]);
                    }

                    $host = $urlException["host"] ?? null;
                    if ($host !== null) {
                        $exception &= array_key_exists("host", $url) && preg_match("/".$host."/", $url["host"]);
                    }

                    $domain = $urlException["domain"] ?? null;
                    if ($domain !== null) {
                        $exception &= array_key_exists("domain", $url) && preg_match("/".$domain."/", $url["domain"]);
                    }

                    $subdomain = $urlException["subdomain"] ?? null;
                    if ($subdomain !== null) {
                        $exception &= array_key_exists("subdomain", $url) && preg_match("/".$subdomain."/", $url["subdomain"]);
                    }

                    $path = $urlException["path"] ?? null;
                    if ($path !== null) {
                        $exception &= array_key_exists("path", $url) && preg_match("/".$path."/", $url["path"]);
                    }

                    if ($exception) {
                        return true;
                    }
                }

                // no break
            default:
                return false;
        }
    }
}
