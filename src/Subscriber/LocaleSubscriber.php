<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Entity\User as BaseUser;

use Base\Service\LocaleProvider;
use Base\Service\LocaleProviderInterface;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    /**
     * @var LocaleProvider
     */
    protected $localeProvider;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    public function __construct(LocaleProviderInterface $localeProvider, RouterInterface $router, TokenStorageInterface $tokenStorage)
    {
        $this->localeProvider = $localeProvider;
        $this->router         = $router;
        $this->tokenStorage   = $tokenStorage;
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
            KernelEvents::REQUEST => ['onKernelRequest', 8],
            SecurityEvents::SWITCH_USER => 'onSwitchUser'
        ];
    }

    public function onSwitchUser(SwitchUserEvent $event): void
    {
        if(!is_instanceof(User::class, BaseUser::class)) return;
        User::setCookie('_locale', $event->getTargetUser()->getLocale());
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;

        $_locale = $this->router->match($event->getRequest()->getPathInfo())["_locale"] ?? null;
        $_locale = $_locale ? $this->localeProvider->getLocale($_locale) : null;

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof BaseUser)
            $_locale = $_locale ?? $user->getLocale();
    
        if($_locale !== null) {

            if(is_instanceof(User::class, BaseUser::class))
                User::setCookie('_locale', $_locale);

            $this->localeProvider->markAsChanged();
            $locale = $_locale;
        }

        $locale ??= $event->getRequest()->cookies->get("_locale");
        if(is_instanceof(User::class, BaseUser::class))
            $locale ??= User::getCookie("locale");

        $locale ??= $this->localeProvider->getLocale();

        // Normalize locale
        $locale = $this->localeProvider->normalize($locale);

        // Set new locale
        $this->localeProvider->setLocale($locale, $event->getRequest());
        $this->localeProvider->markAsLate();
    }
}
