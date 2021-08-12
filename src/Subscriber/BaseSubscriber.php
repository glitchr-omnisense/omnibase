<?php

namespace Base\Subscriber;

use Base\Controller\BaseController;
use Base\Service\BaseService;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BaseSubscriber implements EventSubscriberInterface
{
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public static function getSubscribedEvents()
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse']];
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        BaseController::$foundBaseSubscriber = true;

        if (!$this->baseService->isDebug())
            return;

        $request = $event->getRequest();
        if (!$request->isXmlHttpRequest())
            return;
    
        $response = $event->getResponse();
        $response->headers->set('Symfony-Debug-Toolbar-Replace', 1);
    }
}
