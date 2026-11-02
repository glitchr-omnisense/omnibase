<?php

namespace Base\Service;

use Base\Entity\User\Notification;
use Base\Routing\AdvancedRouterInterface;
use DateTime;
use Base\Service\Model\IntlDateTime;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Omnibridge\Contract\Access;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Launcher implements LauncherInterface
{
    /** @var AdvancedRouterInterface */
    protected AdvancedRouterInterface $router;
    /** @var ParameterBagInterface */
    protected ParameterBagInterface $parameterBag;
    /** @var SettingBagInterface */
    protected SettingBagInterface $settingBag;
    /** @var AuthorizationCheckerInterface */
    /** Who may pass (plan "omnibase agnostic", F5c: Omnibridge\Contract\Access; Security's checker deprecated until 6.0). */
    protected AuthorizationCheckerInterface|Access $authorizationChecker;
    /** @var TokenStorageInterface */
    /** Who is signed in: Access, or Security's token storage (deprecated until 6.0). */
    protected TokenStorageInterface|Access|null $tokenStorage;

    public function __construct(AdvancedRouterInterface $router, ParameterBagInterface $parameterBag, SettingBagInterface $settingBag, #[Autowire(service: Access::class)] AuthorizationCheckerInterface|Access $authorizationChecker, #[Autowire(service: Access::class)] TokenStorageInterface|Access|null $tokenStorage = null)
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

        return self::date($launchdate);
    }

    /**
     * A stored launch date as a date: the setting's DateTime, its text, or a
     * DateTime as JSON holds it ({"date": ...}, a host reading the row itself).
     */
    public static function date(mixed $launchdate): ?DateTime
    {
        if (!$launchdate) {
            return null;
        }
        if ($launchdate instanceof DateTime) return $launchdate;
        if (is_string($launchdate)) return new DateTime($launchdate);
        if (is_array($launchdate) && is_string($launchdate["date"] ?? null)) return new DateTime($launchdate["date"]);
        return null;
    }

    /** The site's years ("2019-2026", "2026"), since a launch date (since()). */
    public static function yearsSince(?DateTime $launchdate): string
    {
        $currentYear = date("Y");
        if (!$launchdate) {
            return $currentYear;
        }

        $launchYear = $launchdate->format("Y");
        $launched = $launchdate < new DateTime("now");
        return $launched && $launchYear < $currentYear ? date("$launchYear-Y") : $launchYear;
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
        return self::yearsSince($this->getLaunchdate($locale));
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
        if ($this->router->isAdmin()) {
            return false;
        }
        if ($this->router->isSecured()) {
            return false;
        }

        if ($this->isLaunched()) {
            $homepageRoute = $this->parameterBag->get("base.site.index");
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

        $user = match (true) {
            $this->tokenStorage instanceof Access => $this->tokenStorage->getUser(),
            null !== $this->tokenStorage => $this->tokenStorage->getToken()?->getUser(),
            $this->authorizationChecker instanceof Access => $this->authorizationChecker->getUser(),
            default => null,
        };
        if ($user && method_exists($user, 'Logout')) {
            $user->Logout();
        }

        return true;
    }
}
