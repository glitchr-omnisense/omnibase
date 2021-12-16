<?php

namespace Base\Subscriber;
use Base\Service\BaseService;
use Base\Entity\User\Notification;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Symfony\Component\HttpKernel\Event\RequestEvent;

class MaintenanceSubscriber implements EventSubscriberInterface
{
    /**
    * @var BaseService
    */
    private $baseService;

    /**
    * @var string
    */
    private $lockPath;

    /**
    * @var string
    */
    private $homepageRoute;
    /**
    * @var array
    */
    private $exceptionRoute;
    /**
    * @var string
    */
    private $maintenanceRoute;

    public function __construct(BaseService $baseService)
    {
    	$this->baseService = $baseService;
        $this->exceptionRoute   = $baseService->getParameterBag("base.maintenance.exception");
        $this->exceptionRoute[] = "security_login";

        $this->homepageRoute = $baseService->getParameterBag("base.maintenance.homepage");
        $this->maintenanceRoute = $baseService->getParameterBag("base.maintenance.redirect");

    }

    public static function getSubscribedEvents(): array
    {
        return [ RequestEvent::class => ['onRequestEvent'] ];
    }

    public function onRequestEvent(RequestEvent $event)
    {
        if ($this->baseService->isCli()) return;
        if ($this->baseService->isProfiler($event->getRequest())) return;

        // Exception triggered
        if( empty($this->getCurrentRoute($event)) ) return;

        // Check if lock file is found or not..
        if(!$this->baseService->isMaintenance()) {

            if(preg_match('/^'.$this->maintenanceRoute.'/', $this->getCurrentRoute($event)))
                $this->baseService->redirectToRoute($this->homepageRoute, [], 302, ["event" => $event]);

            return;
        }

        if($this->baseService->getUser() && $this->baseService->isGranted("ROLE_SUPERADMIN")) {

            $notification = new Notification("maintenance.banner");
            $notification->send("warning");
            return;
        }

        // Disconnect user
        $this->baseService->Logout();

        // Apply redirection to maintenance page
        $isException = preg_match('/^'.$this->maintenanceRoute.'/', $this->getCurrentRoute($event));
        foreach($this->exceptionRoute as $exception)
            $isException |= preg_match('/^'.$exception.'/', $this->getCurrentRoute($event));

        if (!$isException)
            $this->baseService->redirectToRoute($this->maintenanceRoute, [], 302, ["event" => $event]);

        // Stopping page execution
        $event->stopPropagation();
    }

    public function getCurrentRoute($event) { return $event->getRequest()->get('_route'); }
}
