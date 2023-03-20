<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Entity\User as BaseUser;

use Base\Service\Localizer;
use Base\Service\LocalizerInterface;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Timezones;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class LocalizerSubscriber implements EventSubscriberInterface
{
    public const __LANG_IDENTIFIER__ = "LANG";
    public const __TIMEZONE_IDENTIFIER__ = "TIMEZONE";

    /**
     * @var Localizer
     */
    protected $localizer;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    public function __construct(LocalizerInterface $localizer, RouterInterface $router, TokenStorageInterface $tokenStorage)
    {
        $this->localizer = $localizer;
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
        if (!is_instanceof(User::class, BaseUser::class)) {
            return;
        }
        setcookie(self::__LANG_IDENTIFIER__, $event->getTargetUser()->getLocale());
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $_locale = $this->router->match($event->getRequest()->getPathInfo())["_locale"] ?? null;
        $_locale = $_locale ? $this->localizer->getLocale($_locale) : null;

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof BaseUser) {
            $_locale = $_locale ?? $user->getLocale();
        }

        if ($_locale !== null) {
            if (is_instanceof(User::class, BaseUser::class)) {
                setcookie(self::__LANG_IDENTIFIER__, $_locale);
            }

            $this->localizer->markAsChanged();
            $locale = $_locale;
        }

        $locale ??= $event->getRequest()->cookies->get(self::__LANG_IDENTIFIER__);
        if (is_instanceof(User::class, BaseUser::class)) {
            $locale ??= User::getCookie("locale");
        }

        $locale ??= $this->localizer->getLocale();

        // Normalize locale
        $locale = $this->localizer::normalizeLocale($locale);

        // Set new locale
        $this->localizer->setLocale($locale, $event->getRequest());
        $this->localizer->markAsLate();

        //
        // Set timezone
        //
        $this->localizer->setTimezone("UTC");
        if (is_instanceof(User::class, BaseUser::class)) {
            $timezone = User::getCookie("timezone") ?? "UTC";

            $defaultTimezone = date_default_timezone_get();
            if ($timezone != $defaultTimezone) {
                $notification = new Notification("invalidTimezone", [$timezone]);
                $notification->send("info");
            }

            $this->localizer->setTimezone($timezone);

            $country  = User::getCookie("country");
            if ($country) {
                $this->localizer->setCountry($country);
            }
        }
    }
}
