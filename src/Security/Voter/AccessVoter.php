<?php

namespace Base\Security\Voter;

use App\Entity\User;
use Base\Service\LocaleProviderInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\maternityUnitInterface;
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
    const       BIRTH_ACCESS = "BIRTH_ACCESS";
    const MAINTENANCE_ACCESS = "MAINTENANCE_ACCESS";
    const   EXCEPTION_ACCESS = "EXCEPTION_ACCESS";

    const ANONYMOUS_ACCESS = "ANONYMOUS_ACCESS";
    const      USER_ACCESS = "USER_ACCESS";
    const     ADMIN_ACCESS = "ADMIN_ACCESS";

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
     * @var LocaleProvider 
     * */
    protected $localeProvider;
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
     * @var MaternityUnit 
     * */
    protected $maternityUnit;
    
    public function __construct(RequestStack $requestStack, RouterInterface $router, SettingBagInterface $settingBag, ParameterBagInterface $parameterBag, FirewallMapInterface $firewallMap, LocaleProviderInterface $localeProvider, MaintenanceProviderInterface $maintenanceProvider, MaternityUnitInterface $maternityUnit)
    {
        $this->requestStack   = $requestStack;
        $this->router         = $router;
        $this->settingBag     = $settingBag;
        $this->parameterBag   = $parameterBag;
        $this->firewallMap    = $firewallMap;
        $this->localeProvider = $localeProvider;
        $this->maintenanceProvider = $maintenanceProvider;
        $this->maternityUnit    = $maternityUnit;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EXCEPTION_ACCESS, self::MAINTENANCE_ACCESS, self::BIRTH_ACCESS, self::ANONYMOUS_ACCESS, self::USER_ACCESS, self::ADMIN_ACCESS]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user    = $subject instanceof User ? $subject : null;
        $url     = is_string($subject) || $subject instanceof Referrer ? $subject : get_url();

        switch($attribute) {

            case self::ADMIN_ACCESS:
                $access  = filter_var($this->settingBag->getScalar("base.settings.access_restriction.admin_access"), FILTER_VALIDATE_BOOLEAN);
                $access |= $user && $user->isGranted("ROLE_SUPERADMIN");
                return $access;

            case self::USER_ACCESS:
                $access  = filter_var($this->settingBag->getScalar("base.settings.access_restriction.user_access"), FILTER_VALIDATE_BOOLEAN);
                $access |= 
                    filter_var($this->settingBag->getScalar("base.settings.access_restriction.admin_access"), FILTER_VALIDATE_BOOLEAN)
                    && $user && $user->isGranted("ROLE_ADMIN");
                return $access;

            case self::ANONYMOUS_ACCESS:
                $access  = filter_var($this->settingBag->getScalar("base.settings.access_restriction.anonymous_access"), FILTER_VALIDATE_BOOLEAN);
                $access |= (
                    filter_var($this->settingBag->getScalar("base.settings.access_restriction.admin_access"), FILTER_VALIDATE_BOOLEAN) ||
                    filter_var($this->settingBag->getScalar("base.settings.access_restriction.user_access"), FILTER_VALIDATE_BOOLEAN)
                ) && $user && $user->isGranted("ROLE_USER");

                return $access;

            case self::MAINTENANCE_ACCESS:
                return !$this->maintenanceProvider->isUnderMaintenance() || $this->voteOnAttribute(self::EXCEPTION_ACCESS, $subject, $token);

            case self::BIRTH_ACCESS:
                return $this->maternityUnit->isBorn() || $this->voteOnAttribute(self::EXCEPTION_ACCESS, $subject, $token);

            case self::EXCEPTION_ACCESS:

                // Check if firewall is subjected to restriction
                $firewallNames = $this->parameterBag->get("base.access_restriction.firewalls");
                $isRestrictedFirewall = false;

                $firewall = $this->router->getRouteFirewall($url);
                foreach($firewallNames as $firewallName)
                    $isRestrictedFirewall |= $firewall->getName() == $firewallName;

                if(!$isRestrictedFirewall) return true;

                $url = parse_url($url);
                $urlExceptions   = array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", $this->localeProvider->getLocale());
                $urlExceptions ??= array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", $this->localeProvider->getLang());
                $urlExceptions ??= array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", $this->localeProvider->getDefaultLocale());
                $urlExceptions ??= array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", $this->localeProvider->getDefaultLang());
                $urlExceptions ??= array_search_by($this->parameterBag->get("base.access_restriction.exceptions"), "locale", null) ?? [];

                foreach($urlExceptions as $urlException) {

                    $exception = true;

                    $environment = $urlException["env"] ?? null;
                    if($environment !== null) {
                        $exception &= $environment == $this->router->getEnvironment();
                    }

                    $locale = $urlException["locale"] ?? null;
                    if($locale !== null)
                        $exception &= $locale == $this->localeProvider->getLocale() ;

                    $country = $urlException["country"] ?? null;
                    if($country !== null)
                        $exception &= $country == $this->localeProvider->getCountry() ;

                    $lang = $urlException["lang"] ?? null;
                    if($lang !== null)
                        $exception &= $lang == $this->localeProvider->getLang() ;

                    $scheme = $urlException["scheme"] ?? null;
                    if($scheme !== null)
                        $exception &= array_key_exists("scheme", $url) && preg_match("/".$scheme."/", $url["scheme"]);

                    $host = $urlException["host"] ?? null;
                    if($host !== null)
                        $exception &= array_key_exists("host", $url) && preg_match("/".$host."/", $url["host"]);

                    $domain = $urlException["domain"] ?? null;
                    if($domain !== null)
                        $exception &= array_key_exists("domain", $url) && preg_match("/".$domain."/", $url["domain"]);

                    $subdomain = $urlException["subdomain"] ?? null;
                    if($subdomain !== null)
                        $exception &= array_key_exists("subdomain", $url) && preg_match("/".$subdomain."/", $url["subdomain"]);

                    $path = $urlException["path"] ?? null;
                    if($path !== null)
                        $exception &= array_key_exists("path", $url) && preg_match("/".$path."/", $url["path"]);

                    if($exception)
                        return true;
                }

            default:
                return false;
        }
    }
}