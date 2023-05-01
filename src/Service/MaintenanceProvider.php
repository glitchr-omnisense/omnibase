<?php

namespace Base\Service;

use Base\Entity\User\Notification;
use Base\Routing\RouterInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MaintenanceProvider implements MaintenanceProviderInterface
{
    /** @var RouterInterface */
    protected RouterInterface $router;
    /** @var ParameterBagInterface */
    protected ParameterBagInterface $parameterBag;
    /** @var SettingBagInterface */
    protected SettingBagInterface $settingBag;
    /** @var AuthorizationCheckerInterface */
    protected AuthorizationCheckerInterface $authorizationChecker;
    /** @var LocalizerInterface */
    protected LocalizerInterface $localizer;
    /** @var TokenStorageInterface */
    protected TokenStorageInterface $tokenStorage;

    protected int $remainingTime = 0;
    protected int $percentage = -1;
    protected int $uptime = 0;
    protected int $downtime = 0;

    public function __construct(RouterInterface $router, SettingBagInterface $settingBag, AuthorizationCheckerInterface $authorizationChecker, ParameterBagInterface $parameterBag, LocalizerInterface $localizer, TokenStorageInterface $tokenStorage)
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

    protected function parseLockPath(): self
    {
        if ($this->lockPath) {
            return $this;
        }
        $this->lockPath = $this->parameterBag->get("base.maintenance.lockpath");

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
        $this->parseLockPath();

        if (filter_var($this->settingBag->getScalar("base.settings.maintenance", $this->localizer->getLocale()))) {
            return true;
        }
        if ($this->lockPath && file_exists($this->lockPath)) {
            return true;
        }

        return false;
    }

    public function redirectOnDeny(?RequestEvent $event = null): bool
    {
        $this->parseLockPath();

        $redirectOnDeny = $this->parameterBag->get("base.site.maintenance.redirect_on_deny") ?? "security_maintenance";
        if (!$this->isUnderMaintenance()) {
            $homepageRoute = $this->parameterBag->get("base.site.homepage");
            if ($event && $redirectOnDeny == $this->router->getRouteName()) {
                $event->setResponse($this->router->redirect($homepageRoute));
            }

            return false;
        } elseif ($this->authorizationChecker->isGranted("ROLE_EDITOR")) {
            $notification = new Notification("maintenance.banner");
            $notification->send("warning");
            return false;
        }

        if ($this->router->getRouteName() == $redirectOnDeny) {
            return true;
        }
        if ($this->authorizationChecker->isGranted("MAINTENANCE_ACCESS")) {
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
