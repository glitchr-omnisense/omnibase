<?php

namespace Base\Subscriber;
use Base\Service\BaseService;
use Base\Entity\User\Notification;
use Base\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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

    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag, BaseService $baseService)
    {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->baseService = $baseService;

        $this->exceptionRoute   = $parameterBag->get("base.maintenance.exception");
        $this->exceptionRoute[] = "security_rescue";

        $this->homepageRoute = $parameterBag->get("base.maintenance.homepage");
        $this->maintenanceRoute = $parameterBag->get("base.maintenance.redirect");
    }

    public static function getSubscribedEvents(): array
    {
        return [ RequestEvent::class => ['onRequestEvent'] ];
    }

    public function onRequestEvent(RequestEvent $event)
    {
        // Exception triggered
        if( empty( $this->router->getRouteName()) ) return;

        if(!$this->baseService->isMaintenance()) {

            if(preg_match('/^'.$this->maintenanceRoute.'/', $this->router->getRouteName()))
                $this->router->redirectToRoute($this->homepageRoute, [], 302, ["event" => $event]);

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
        $isException = preg_match('/^'.$this->maintenanceRoute.'/', $this->router->getRouteName());
        foreach($this->exceptionRoute as $exception)
            $isException |= preg_match('/^'.$exception.'/', $this->router->getRouteName());

        if (!$isException)
            $this->router->redirectToRoute($this->maintenanceRoute, [], 302, ["event" => $event]);

        // Stopping page execution
        $event->stopPropagation();
    }
}
