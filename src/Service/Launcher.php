<?php

namespace Base\Service;

use Base\Entity\User\Notification;
use Base\Routing\AdvancedRouterInterface;
use DateTime;
use Base\Service\Model\IntlDateTime;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class Launcher implements LauncherInterface
{
    /** @var AdvancedRouterInterface */
    protected AdvancedRouterInterface $router;
    /** @var ParameterBagInterface */
    protected ParameterBagInterface $parameterBag;
    /** @var SettingBagInterface */
    protected SettingBagInterface $settingBag;
    /** @var AuthorizationCheckerInterface */
    protected AuthorizationCheckerInterface $authorizationChecker;
    /** @var TokenStorageInterface */
    protected TokenStorageInterface $tokenStorage;

    public function __construct(AdvancedRouterInterface $router, ParameterBagInterface $parameterBag, SettingBagInterface $settingBag, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage)
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

        if ($launchdate instanceof DateTime) return $launchdate;
        if (is_string($launchdate)) return new DateTime($launchdate);
        return null;
    }

    public function isLaunched(?string $locale = null): ?bool
    {
        $launchdate = $this->getLaunchdate($locale);
        if ($launchdate === null) {
            return false;
        }

        $now = new DateTime("now");
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
                $event->setResponse($this->router->redirect($homepageRoute));
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
            // Same bug as MaintenanceProvider: the 4th argument of
            // redirectToRoute() is $headers, so the event ended up in a
            // RedirectResponse header bag and threw. redirectEvent() is the
            // one that sets the response on the event.
            $this->router->redirectEvent($event, $redirectOnDeny, [], 302);
        }

        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser()) {
            $token->getUser()->Logout();
        }

        return true;
    }
}
