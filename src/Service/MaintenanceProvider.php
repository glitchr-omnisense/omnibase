<?php

namespace Base\Service;

use Base\Request\EventDecisions;
use Base\Request\RouterPage;
use Base\Request\Rule\MaintenancePage;
use Base\Routing\AdvancedRouterInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Omnibridge\Contract\Access;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MaintenanceProvider implements MaintenanceProviderInterface
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
    /** @var LocalizerInterface */
    protected LocalizerInterface $localizer;
    /** @var TokenStorageInterface */
    /** Who is signed in: Access, or Security's token storage (deprecated until 6.0). */
    protected TokenStorageInterface|Access|null $tokenStorage;

    protected int $remainingTime = 0;
    protected int $percentage = -1;
    protected int $uptime = 0;
    protected int $downtime = 0;

    public function __construct(AdvancedRouterInterface $router, SettingBagInterface $settingBag, #[Autowire(service: Access::class)] AuthorizationCheckerInterface|Access $authorizationChecker, ParameterBagInterface $parameterBag, LocalizerInterface $localizer, #[Autowire(service: Access::class)] TokenStorageInterface|Access|null $tokenStorage = null)
    {
        $this->router = $router;

        $this->settingBag = $settingBag;
        $this->localizer = $localizer;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->parameterBag = $parameterBag;
    }

    protected ?string $lockPath = null;
    protected bool $ready = false;

    // Request-scoped cache for maintenance status
    private ?bool $maintenanceStatusCache = null;

    protected function parseLockPath(): self
    {
        if ($this->lockPath) {
            return $this;
        }
        $this->lockPath = $this->parameterBag->get("base.site.maintenance.lockpath");

        $uptime = null;
        $downtime = null;
        if ($this->lockPath && ($f = @fopen($this->lockPath, "r"))) {
            $downtime = trim(fgets($f, 4096));
            if (!feof($f)) {
                $uptime = trim(fgets($f, 4096));
            }

            fclose($f);
        } else {
            $downtime = $this->settingBag->get("base.settings.maintenance.downtime")["_self"] ?? null;
            $uptime = $this->settingBag->get("base.settings.maintenance.uptime")["_self"] ?? null;
        }

        $downtime = $downtime?->getTimestamp() ?? 0;
        $uptime = $uptime?->getTimestamp() ?? 0;

        $this->remainingTime = $uptime ? $uptime - time() : 0;
        if ($downtime - time() > 0 || $downtime < 1) {
            $downtime = 0;
        }
        if ($uptime - time() < 0 || $uptime < 1) {
            $uptime = 0;
        }

        $this->percentage = -1;
        if ($downtime && ($uptime - $downtime > 0) && ($uptime - time() > 0)) {
            $this->percentage = round(100 * (time() - $downtime) / ($uptime - $downtime));
        }

        $this->uptime = $uptime;
        $this->downtime = $downtime;
        return $this;
    }

    public function getRemainingTime(): int
    {
        return $this->parseLockPath()->remainingTime;
    }

    public function getPercentage(): int
    {
        return $this->parseLockPath()->percentage;
    }

    public function getDowntime(): int
    {
        return $this->parseLockPath()->downtime;
    }

    public function getUptime(): int
    {
        return $this->parseLockPath()->uptime;
    }

    public function isUnderMaintenance(): bool
    {
        // Return cached result if available (avoids repeated file I/O)
        if ($this->maintenanceStatusCache !== null) {
            return $this->maintenanceStatusCache;
        }

        $this->parseLockPath();

        $result = false;
        if (filter_var($this->settingBag->getScalar("base.settings.maintenance", $this->localizer->getLocale()))) {
            $result = true;
        } elseif ($this->lockPath && file_exists($this->lockPath)) {
            $result = true;
        }

        // Cache the result for the duration of the request
        $this->maintenanceStatusCache = $result;
        return $result;
    }

    /**
     * The maintenance page's decision applied to a Symfony request event (deprecated until 6.0: the rule is
     * Base\Request\Rule\MaintenancePage, which omnibridge/symfony's SecuritySubscriber asks itself). True
     * when the request stops there.
     */
    public function redirectOnDeny(?RequestEvent $event = null): bool
    {
        $this->parseLockPath();
        $decision = (new MaintenancePage($this, $this->parameterBag->get("base.site.maintenance.redirect_on_deny"), $this->parameterBag->get("base.site.index")))
            ->decide(new RouterPage($this->router), fn (string $attribute): bool => $this->authorizationChecker->isGranted($attribute));

        return $this->applyDecision($decision, $event);
    }
}
