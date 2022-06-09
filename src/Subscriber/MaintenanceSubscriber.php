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
        $this->exceptionRoute[] = "security_rescue";

        $this->homepageRoute = $baseService->getParameterBag("base.maintenance.homepage");
        $this->maintenanceRoute = $baseService->getParameterBag("base.maintenance.redirect");
    }

    public static function getSubscribedEvents(): array
    {
        return [ RequestEvent::class => ['onRequestEvent'] ];
    }

    public function getCurrentRouteName($event) { return $event->getRequest()->get('_route'); }

    public function onRequestEvent(RequestEvent $event)
    {
        // Exception triggered
        if( empty($this->getCurrentRouteName($event)) ) return;

        if(!$this->baseService->isMaintenance()) {

            if(preg_match('/^'.$this->maintenanceRoute.'/', $this->getCurrentRouteName($event)))
                $this->baseService->redirectToRoute($this->homepageRoute, [], 302, ["event" => $event]);

            return;
        }

        if($this->baseService->getUser() && $this->baseService->isGranted("ROLE_EDITOR")) {

            $notification = new Notification("maintenance.banner");
            $notification->send("warning");
            return;
        }

        // Disconnect user
        $this->baseService->Logout();

        // Apply redirection to maintenance page
        $isException = preg_match('/^'.$this->maintenanceRoute.'/', $this->getCurrentRouteName($event));
        foreach($this->exceptionRoute as $exception)
            $isException |= preg_match('/^'.$exception.'/', $this->getCurrentRouteName($event));

        if (!$isException)
            $this->baseService->redirectToRoute($this->maintenanceRoute, [], 302, ["event" => $event]);

        // Stopping page execution
        $event->stopPropagation();
    }
}
