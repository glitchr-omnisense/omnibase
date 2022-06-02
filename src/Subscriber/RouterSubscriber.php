<?php

namespace Base\Subscriber;

use Base\Service\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Base\Service\Settings;

class RouterSubscriber implements EventSubscriberInterface
{
    /**
     * @var Router
     */
    protected $router;

    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag, Settings $settings)
    {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->settings = $settings;
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
        if(!$this->authorizationChecker->isGranted("ROUTER_IP", $route)) {
            $event->setResponse($this->redirectByReduction(true, true, null, $this->settings->host()));
            return $event->stopPropagation();
        }

        //
        // If no host specified in Route, then check the list of permitted subdomain
        if(!$this->authorizationChecker->isGranted("ROUTE_HOST", $route)) {
            $event->setResponse($this->redirectByReduction(false, true));
            return $event->stopPropagation();
        }
    }

    public function redirectByReduction(
        bool $keep_subdomain = true, bool $keep_machine = true,
        ?string $scheme = null,
        ?string $http_host = null,
        ?string $request_uri = null): RedirectResponse
    {
        return new RedirectResponse(get_url($keep_subdomain, $keep_machine, $scheme, $http_host, $request_uri));
    }
}
