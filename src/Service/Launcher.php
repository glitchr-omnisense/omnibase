<?php

namespace Base\Service;

use Base\Entity\User\Notification;
use Base\Routing\RouterInterface;
use DateTime;
use Base\Service\Model\IntlDateTime;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class Launcher implements LauncherInterface
{
    /** @var Router */
    protected $router;
    /** @var ParameterBag */
    protected $parameterBag;
    /** @var SettingBag */
    protected $settingBag;
    /** @var AuthorizationChecker */
    protected $authorizationChecker;
    /** @var TokenStorage */
    protected $tokenStorage;

    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag, SettingBagInterface $settingBag, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage)
    {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->settingBag = $settingBag;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
    }

    public function getLaunchdate(?string $locale = null): ?DateTime
    {
        $launchdate = $this->settingBag->getScalar("base.settings.launchdate", $locale);
        if (!$launchdate) {
            return null;
        }

        return $launchdate instanceof DateTime ? $launchdate : new DateTime($launchdate);
    }

    public function isLaunched(?string $locale = null): ?bool
    {
        $launchdate = $this->getLaunchdate($locale);
        if ($launchdate === null) {
            return false;
        }

        $now = new \DateTime("now");
        return ($launchdate < $now);
    }

    public function since(?string $locale = null): string
    {
        $currentYear = date("Y");
        $launchdate = $this->getLaunchdate($locale);
        if (!$launchdate) {
            return $currentYear;
        }

        $launchYear = $launchdate->format("Y");
        return $this->isLaunched($locale) && $launchYear < $currentYear ? date("$launchYear-Y") : $launchYear;
    }

    public function redirectOnDeny(?RequestEvent $event = null, ?string $locale = null): bool
    {
        if (!$this->settingBag->getScalar("base.settings.launchdate.redirect_on_deny")) {
            return false;
        }
        if (!$this->getLaunchdate()) {
            return false;
        }

        $redirectOnDeny = "security_launch";
        if ($this->router->isUX()) {
            return false;
        }
        if ($this->router->isProfiler()) {
            return false;
        }
        if ($this->router->isEasyAdmin()) {
            return false;
        }
        if ($this->router->isSecured()) {
            return false;
        }

        if ($this->isLaunched()) {
            $homepageRoute = $this->parameterBag->get("base.site.homepage");
            if ($event && $redirectOnDeny == $this->router->getRouteName()) {
                $event->setResponse($this->router->redirect($homepageRoute, [], 302));
            }

            return false;
        } elseif ($this->authorizationChecker->isGranted("ROLE_EDITOR")) {
            $launchdate = $this->getLaunchdate();
            $notification = new Notification("launcher.banner", [IntlDateTime::createFromDateTime($launchdate, $locale)->format("dd MMMM YYYY"), IntlDateTime::createFromDateTime($launchdate, $locale)->format("HH:mm")]);
            $notification->send("warning");
            return false;
        }

        if ($this->router->getRouteName() == $redirectOnDeny) {
            return false;
        }
        if ($this->authorizationChecker->isGranted("LAUNCH_ACCESS")) {
            return false;
        }

        if ($event) {
            $this->router->redirectToRoute($redirectOnDeny, [], 302, ["event" => $event]);
        }

        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser()) {
            $token->getUser()->Logout();
        }

        return true;
    }
}
