<?php

namespace Base\Subscriber;

use Base\Service\BaseService;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfilerSubscriber implements EventSubscriberInterface
{
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 256],
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if($this->baseService->isProfiler($event) && !$this->baseService->isDebug())
            throw new NotFoundHttpException("Page not found.");
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if ($this->baseService->isDebug()) {

            $request = $event->getRequest();
            if ($request->isXmlHttpRequest()) {

                $response = $event->getResponse();
                $response->headers->set('Symfony-Debug-Toolbar-Replace', true);

                return true;
            }
        }

        return false;
    }
}
