<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Service\BaseService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Twig\Environment;

class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(Security $security, BaseService $baseService)
    {
        $this->security = $security;
        $this->defaultLocale = $baseService->getParameterBag("kernel.default_locale");
    }

    public static function getSubscribedEvents()
    {
         /* 
          * Must be set prior SecuritySubscriber and 
          * after Symfony\Component\HttpKernel\EventListener\LocaleListener::setDefaultLocale()
          *
          * CLI: php bin/console debug:event kernel.request
          */
        return [ 
            KernelEvents::REQUEST => ['onKernelRequest', 128]
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        $locale = explode("-", User::getCookie("locale") ?? $this->defaultLocale)[0];
        $request->setLocale($locale);
    }
}
