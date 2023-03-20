<?php

namespace Base\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class FlashBagSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10]
        ];
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();

        /**
         * @var Session
         */
        $session = $event->getRequest()->getSession();
        if ($response instanceof JsonResponse) {
            $flashMessages = $session->getFlashBag()->all();
            if (!empty($flashMessages)) {
                $data = json_decode($response->getContent(), true);
                $data['flashbag'] = $flashMessages;

                $response->setData($data);
            }
        }
    }
}
