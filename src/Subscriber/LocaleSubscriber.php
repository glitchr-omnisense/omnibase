<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Service\LocaleProviderInterface;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(LocaleProviderInterface $localeProvider, TranslatorInterface $translator, RouterInterface $router)
    {
        $this->localeProvider = $localeProvider;
        $this->router         = $router;
        $this->translator     = $translator;
    }
    public static function getSubscribedEvents(): array
    {
         /* 
          * Must be set prior SecuritySubscriber and 
          * after Symfony\Component\HttpKernel\EventListener\LocaleListener::setDefaultLocale()
          *
          * CLI: php bin/console debug:event kernel.request
          */
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 128],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;

        $locale = $event->getRequest()->getSession()->get("_locale") 
               ?? User::getCookie("locale")
               ?? $this->localeProvider->getLocale();

        $this->localeProvider->setLocale($locale, $event->getRequest());
        $this->localeProvider->markAsLate();
    }
}
