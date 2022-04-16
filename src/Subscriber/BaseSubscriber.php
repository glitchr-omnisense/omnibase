<?php

namespace Base\Subscriber;

use Base\Service\BaseService;
use InvalidArgumentException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BaseSubscriber implements EventSubscriberInterface
{
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand'],
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

    // Make sure base service is called eagerly in the very early stage 
    public function onConsoleCommand() { return null; }

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
