<?php

namespace Base\Service;

use Base\Entity\User\Notification;
use Base\Routing\RouterInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MaintenanceProvider implements MaintenanceProviderInterface
{
    protected $router;
    public function __construct(RouterInterface $router, SettingBagInterface $settingBag, AuthorizationCheckerInterface $authorizationChecker, ParameterBagInterface $parameterBag, LocaleProviderInterface $localeProvider, TokenStorageInterface $tokenStorage)
    {
        $this->router = $router;
        
        $this->settingBag = $settingBag;
        $this->localeProvider = $localeProvider;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->parameterBag = $parameterBag;
    }

    protected function parseLockPath(?string $fname)
    {
        if ( $fname && ($f = @fopen($fname, "r")) ) {
    
            $downtime = trim(fgets($f, 4096));
            if(!feof($f)) $uptime = trim(fgets($f, 4096));
    
            fclose($f);
    
        } else {
    
            $downtime = $this->settingBag->get("base.settings.maintenance.downtime")["_self"] ?? null;
            $uptime   = $this->settingBag->get("base.settings.maintenance.uptime")["_self"] ?? null;
        }
    
        $downtime = $downtime ? strtotime($downtime) : 0;
        $uptime = $uptime ? strtotime($uptime) : 0;

        $this->remainingTime = $uptime ? $uptime - time() : 0;
        if ($downtime-time() > 0 || $downtime < 1) $downtime = 0;
        if (  $uptime-time() < 0 || $uptime < 1) $uptime = 0;
    
        $this->percentage = -1;
        if( $downtime && ($uptime-$downtime > 0) && ($uptime-time() > 0) )
            $this->percentage = round(100 * (time()-$downtime)/($uptime-$downtime));

        $this->uptime   = $uptime;
        $this->downtime = $downtime;
    }

    public function getRemainingTime():int { return $this->remainingTime; }
    public function getPercentage()   :int { return $this->percentage; }

    public function getDowntime()     :int { return $this->downtime; }
    public function getUptime()       :int { return $this->uptime; }

    public function isUnderMaintenance():bool
    { 
        if(filter_var($this->settingBag->getScalar("base.settings.maintenance", $this->localeProvider->getLocale()))) return true;
        if(file_exists($this->parameterBag->get("base.site.maintenance.lockpath"))) return true;

        return false;
    }

    public function redirectOnDeny(?RequestEvent $event = null): bool
    {
        $redirectOnDeny = $this->parameterBag->get("base.site.maintenance.redirect_on_deny");
        if(!$this->isUnderMaintenance()) {
            
            $homepageRoute = $this->parameterBag->get("base.site.homepage");
            if(preg_match('/^'.$redirectOnDeny.'/', $this->router->getRouteName())) {

                $event->setResponse($this->router->redirect($homepageRoute, [], 302));
                return false;
            }
        }

        if($this->authorizationChecker->isGranted("ROLE_EDITOR")) {

            $notification = new Notification("maintenance.banner");
            $notification->send("warning");
            return false;
        }

        if ($this->authorizationChecker->isGranted("MAINTENANCE_ACCESS"))
            return false;

        $this->router->redirectToRoute($redirectOnDeny, [], 302, ["event" => $event]);
        
        $token = $this->tokenStorage->getToken();
        if($token && $token->getUser()) $token->getUser()->Logout();

        return true;
    }
}
