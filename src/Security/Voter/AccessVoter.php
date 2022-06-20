<?php

namespace Base\Security\Voter;

use App\Entity\User;
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
    const   EXCEPTION_ACCESS = "EXCEPTION_ACCESS";
    const MAINTENANCE_ACCESS = "MAINTENANCE_ACCESS";

    const ANONYMOUS_ACCESS = "ANONYMOUS_ACCESS";
    const      USER_ACCESS = "USER_ACCESS";
    const     ADMIN_ACCESS = "ADMIN_ACCESS";

    public function __construct(RequestStack $requestStack, RouterInterface $router, SettingBagInterface $settingBag, ParameterBagInterface $parameterBag, FirewallMapInterface $firewallMap)
    {
        $this->requestStack = $requestStack;
        $this->router       = $router;
        $this->settingBag   = $settingBag;
        $this->parameterBag = $parameterBag;
        $this->firewallMap  = $firewallMap;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EXCEPTION_ACCESS, self::MAINTENANCE_ACCESS, self::ANONYMOUS_ACCESS, self::USER_ACCESS, self::ADMIN_ACCESS]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user    = $subject instanceof User ? $subject : null;
        $url     = is_string($subject) || $subject instanceof Referrer ? $subject : get_url();

        //
        // Select proper ballot
        switch($attribute) {

            case self::ADMIN_ACCESS:
                $access  = filter_var($this->settingBag->getScalar("base.settings.admin_access"), FILTER_VALIDATE_BOOLEAN);
                $access |= $user && $user->isGranted("ROLE_EDITOR");
                return $access;

            case self::USER_ACCESS:
                $access  = filter_var($this->settingBag->getScalar("base.settings.admin_access"), FILTER_VALIDATE_BOOLEAN);
                $access &= filter_var($this->settingBag->getScalar("base.settings.user_access"), FILTER_VALIDATE_BOOLEAN);
                $access |= $user && $user->isGranted("ROLE_ADMIN");
                return $access;

            case self::ANONYMOUS_ACCESS:
                $access  = filter_var($this->settingBag->getScalar("base.settings.admin_access"), FILTER_VALIDATE_BOOLEAN);
                $access &= filter_var($this->settingBag->getScalar("base.settings.user_access"), FILTER_VALIDATE_BOOLEAN);
                $access &= filter_var($this->settingBag->getScalar("base.settings.anonymous_access"), FILTER_VALIDATE_BOOLEAN);
                $access |= $user && $user->isGranted("ROLE_USER");
                return $access;

            case self::MAINTENANCE_ACCESS:
                return !$this->settingBag->maintenance() && !file_exists($this->parameterBag->get("base.maintenance.lockpath"));

            case self::EXCEPTION_ACCESS:

                // Check if firewall is subjected to restriction
                $firewallNames = $this->parameterBag->get("base.access_restriction.firewalls");
                $isRestrictedFirewall = false;

                foreach($firewallNames as $firewallName)
                    $isRestrictedFirewall |= $this->router->getRouteFirewall($url) == $firewallName;

                if(!$isRestrictedFirewall) return true;

                $url = parse_url($url);
                $urlExceptions  = $this->parameterBag->get("base.access_restriction.exceptions");
                foreach($urlExceptions as $urlException) {

                    if(preg_match("/".$urlException."/", $url["host"] ?? ""))
                        return true;
                }

            default:
                return false;
        }
    }
}