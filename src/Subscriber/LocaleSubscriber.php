<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Service\ReferrerInterface;
use Base\Service\LocaleProvider;
use Base\Service\LocaleProviderInterface;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocaleSubscriber implements EventSubscriberInterface
{
    /**
     * @var LocaleProvider
     */
    protected $localeProvider;

    public function __construct(LocaleProviderInterface $localeProvider, RouterInterface $router, TranslatorInterface $translator)
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
        return [ KernelEvents::REQUEST => ['onKernelRequest', 128] ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;

        $_locale = $this->router->match($event->getRequest()->getPathInfo())["_locale"] ?? null;
        $_locale = $_locale ? $this->localeProvider->getLocale($_locale) : null;
        if($_locale !== null) {

            $event->getRequest()->getSession()->set('_locale', $_locale);
            $this->localeProvider->markAsChanged();
            $locale = $_locale;
        }

        $locale ??= $event->getRequest()->getSession()->get("_locale");
        $locale ??= User::getCookie("locale");
        $locale ??= $this->localeProvider->getLocale();

        // Normalize locale
        $locale = $this->localeProvider->normalize($locale);

        // Set new locale
        $this->localeProvider->setLocale($locale, $event->getRequest());
        $this->localeProvider->markAsLate();
    }
}
