<?php

namespace Base\Service;

use Base\Request\EventDecisions;
use Base\Request\RouterPage;
use Base\Request\Rule\LaunchPage;
use Base\Routing\AdvancedRouterInterface;
use DateTime;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Omnibridge\Contract\Access;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Launcher implements LauncherInterface
{
    use EventDecisions;

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

    /**
     * The launch page's decision applied to a Symfony request event (deprecated until 6.0: the rule is
     * Base\Request\Rule\LaunchPage, which omnibridge/symfony's SecuritySubscriber asks itself). True when
     * the request stops there.
     */
    public function redirectOnDeny(?RequestEvent $event = null, ?string $locale = null): bool
    {
        $decision = (new LaunchPage($this, (bool) $this->settingBag->getScalar("base.settings.launchdate.redirect_on_deny"), $this->parameterBag->get("base.site.index")))
            ->decide(new RouterPage($this->router), fn (string $attribute): bool => $this->authorizationChecker->isGranted($attribute), $locale);

        return $this->applyDecision($decision, $event);
    }
}
