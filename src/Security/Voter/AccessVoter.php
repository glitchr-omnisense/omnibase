<?php

namespace Base\Security\Voter;

use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AccessVoter extends Voter
{
    const EXCEPTION_ACCESS = "EXCEPTION_ACCESS";

    const    PUBLIC_ACCESS = "PUBLIC_ACCESS";
    const      USER_ACCESS = "USER_ACCESS";
    const     ADMIN_ACCESS = "ADMIN_ACCESS";
    const    EDITOR_ACCESS = "EDITOR_ACCESS";

    public function __construct(RequestStack $requestStack, RouterInterface $router, SettingBagInterface $settingBag, ParameterBagInterface $parameterBag)
    {
        $this->requestStack = $requestStack;
        $this->router       = $router;
        $this->settingBag   = $settingBag;
        $this->parameterBag = $parameterBag;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EXCEPTION_ACCESS, self::PUBLIC_ACCESS, self::USER_ACCESS, self::ADMIN_ACCESS, self::EDITOR_ACCESS]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $request = $this->requestStack->getCurrentRequest();

        //
        // Select proper ballot
        switch($attribute) {

            case self::PUBLIC_ACCESS:

                $publicAccess  = filter_var($this->settingBag->getScalar("base.settings.public_access"), FILTER_VALIDATE_BOOLEAN);
                $publicAccess |= $user && $user->isGranted("ROLE_USER");

                return $publicAccess;

            case self::USER_ACCESS:

                $userAccess    = filter_var($this->settingBag->getScalar("base.settings.user_access"), FILTER_VALIDATE_BOOLEAN);
                $userAccess   |= $user && $user->isGranted("ROLE_ADMIN");

                return $userAccess;

            case self::ADMIN_ACCESS:

                $adminAccess   = filter_var($this->settingBag->getScalar("base.settings.admin_access"), FILTER_VALIDATE_BOOLEAN);
                $adminAccess  |= $user && $user->isGranted("ROLE_EDITOR");

                return $adminAccess;

            case self::EDITOR_ACCESS:
                return $user && $user->isGranted("ROLE_EDITOR");

            case self::EXCEPTION_ACCESS:

                // Check if firewall is subjected to restriction
                $firewallNames = $this->parameterBag->get("base.access_restriction.firewalls");

                $isRestrictedFirewall = false;
                foreach($firewallNames as $firewallName)
                    $isRestrictedFirewall |= $request->attributes->get("_firewall_context") == "security.firewall.map.context.".$firewallName;
                if(!$isRestrictedFirewall) return true;

                $url = parse_url(get_url());
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