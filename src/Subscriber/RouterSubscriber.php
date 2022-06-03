<?php

namespace Base\Subscriber;

use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class RouterSubscriber implements EventSubscriberInterface
{
    /**
     * @var Router
     */
    protected $router;

    public function __construct(AuthorizationChecker $authorizationChecker, RouterInterface $router, ParameterBagInterface $parameterBag, SettingBagInterface $settingBag)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->settingBag = $settingBag;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST  => ['onKernelRequest', 256]];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $route = $this->router->getRoute();

        //
        // Redirect IP if restriction enabled
        $url = parse_url2();
        $host = $this->settingBag->host();
        if(array_key_exists("ip", $url) && !$this->authorizationChecker->isGranted("ROUTE_IP", $route)) {

            $event->setResponse($this->redirect(true, true, null, $host));
            return $event->stopPropagation();
        }

        //
        // If no host specified in Route, then check the list of permitted subdomain
        if(array_key_exists("host", $url) && !$this->authorizationChecker->isGranted("ROUTE_HOST", $route) && $url["host"] !== $host) {
            $event->setResponse($this->redirect(false, true));
            return $event->stopPropagation();
        }
    }

    public function redirect(
        bool $keep_subdomain = true, bool $keep_machine = true,
        ?string $scheme = null,
        ?string $http_host = null,
        ?string $request_uri = null): RedirectResponse
    {
        return new RedirectResponse(get_url($keep_subdomain, $keep_machine, $scheme, $http_host, $request_uri));
    }
}
