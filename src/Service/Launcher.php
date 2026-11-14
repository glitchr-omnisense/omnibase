<?php

namespace Base\Service;

use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface;
use DateTime;
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
    /** Who may pass (plan "omnibase agnostic", F5c: Omnibridge\Contract\Access). */
    protected Access $authorizationChecker;
    /** Who is signed in. */
    protected ?Access $tokenStorage;

    public function __construct(AdvancedRouterInterface $router, ParameterBagInterface $parameterBag, SettingBagInterface $settingBag, #[Autowire(service: Access::class)] Access $authorizationChecker, #[Autowire(service: Access::class)] ?Access $tokenStorage = null)
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
}
