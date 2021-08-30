<?php

namespace Base\Subscriber;

use App\Entity\User;
use App\Entity\User\Notification;

use Base\Service\BaseService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Twig\Environment;

class TimezoneSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest']
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $timezone = User::getCookie("timezone") ?? "UTC";
        $defaultTimezone = date_default_timezone_get();

        if($timezone != $defaultTimezone) {
            $notification = new Notification("notifications.invalidTimezone", [$timezone]);
            $notification->send("info");
        }
    }
}
