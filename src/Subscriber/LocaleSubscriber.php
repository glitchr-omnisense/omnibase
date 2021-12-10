<?php

namespace Base\Subscriber;

use Base\Service\LocaleProviderInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(LocaleProviderInterface $localeProvider)
    {
        $this->localeProvider = $localeProvider;
    }
    public static function getSubscribedEvents(): array
    {
         /* 
          * Must be set prior SecuritySubscriber and 
          * after Symfony\Component\HttpKernel\EventListener\LocaleListener::setDefaultLocale()
          *
          * CLI: php bin/console debug:event kernel.request
          */
        return [KernelEvents::REQUEST => ['onKernelRequest', 128]];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $this->localeProvider->markAsLate();

        $locale = substr_replace($this->localeProvider->getLocale(), "_", 2, 1);
        
        $request = $event->getRequest();
        $request->setLocale($locale);
    }
}
