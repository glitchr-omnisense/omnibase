<?php

namespace Base\Service;

use Base\Entity\User\Notification;
use Base\Routing\RouterInterface;
use DateTime;
use Base\Model\IntlDateTime;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MaternityService implements MaternityServiceInterface
{
    protected $router;
    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag, SettingBagInterface $settingBag, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage)
    {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->settingBag = $settingBag;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
    }

    public function getBirthdate(?string $locale = null) : ?DateTime
    {
        $birthdate = $this->settingBag->getScalar("base.settings.birthdate", $locale);
        if(!$birthdate) return null;

        return $birthdate instanceof DateTime ? $birthdate : new DateTime($birthdate);
    }

    public function isBorn(?string $locale = null) : bool
    { 
        $now = new \DateTime("now");
        $birthdate = $this->getBirthdate($locale);

        return ($birthdate < $now);
    }

    public function getAge(?string $locale = null) : string
    {
        $currentYear = date("Y");
        $birthdate = $this->getBirthdate($locale);
        if(!$birthdate) return $currentYear;

        $birthYear = $birthdate->format("Y");
        return $this->isBorn($locale) && $birthYear < $currentYear ? date("$birthYear-Y") : $birthYear;
    }

    public function redirectOnDeny(?RequestEvent $event = null, ?string $locale = null): bool
    {
        $redirectOnDeny = $this->parameterBag->get("base.site.birthdate.redirect_on_deny");
        if($this->router->isProfiler() ) return false;
        if($this->router->isEasyAdmin()) return false;
        if($this->isBorn()) {

            $homepageRoute = $this->parameterBag->get("base.site.homepage");
            if(preg_match('/^'.$redirectOnDeny.'/', $this->router->getRouteName())) {

                $event->setResponse($this->router->redirect($homepageRoute, [], 302));
                return false;
            }

            return false;
        }

        if($this->authorizationChecker->isGranted("ROLE_EDITOR")) {

            $birthdate = $this->getBirthdate();
            $notification = new Notification("maternity.banner", [IntlDateTime::createFromDateTime($birthdate, $locale)->format("dd MMMM YYYY"), IntlDateTime::createFromDateTime($birthdate, $locale)->format("HH:mm")]);
            $notification->send("warning");
            return false;
        }

        if ($this->authorizationChecker->isGranted("BIRTH_ACCESS"))
            return false;

        if ($this->router->getRouteName() != "security_birth")
            $this->router->redirectToRoute("security_birth", [], 302, ["event" => $event]);

        $token = $this->tokenStorage->getToken();
        if($token && $token->getUser()) $token->getUser()->Logout();

        return true;
    }
}
