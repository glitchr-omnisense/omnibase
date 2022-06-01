<?php

namespace Base\Subscriber;

use Base\Service\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Base\Service\BaseSettings;

class RouterSubscriber implements EventSubscriberInterface
{
    /**
     * @var Router
     */
    protected $router;

    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag, BaseSettings $baseSettings)
    {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->baseSettings = $baseSettings;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST  => ['onKernelRequest', 256]];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $url = parse_url2(get_url());

        //
        // Redirect IP if restriction enabled
        if(array_key_exists("ip", $url) && !$this->parameterBag->get("base.host_restriction.ip_access")) {
            $event->setResponse($this->redirectByReduction(true, true, null, $this->baseSettings->host()));
            return $event->stopPropagation();
        }

        $route = $this->router->getRoute();
        if(!$route) return;

        //
        // If no host specified in Route, then check the list of permitted subdomain
        if(empty($route->getHost())) {

            $reduce = !$this->router->keepMachine() || !$this->router->keepSubdomain();
            if(array_key_exists("machine", $url) && $reduce) {

                $event->setResponse($this->redirectByReduction(false, true));
                return $event->stopPropagation();
            }

            $vetoSubdomain = true;
            $permittedSubdomains = $this->parameterBag->get("base.host_restriction.permitted_subdomains") ?? [];
            foreach($permittedSubdomains ?? [] as $permittedSubdomain)
                $vetoSubdomain &= !preg_match("/".$permittedSubdomain."/", $url["subdomain"] ?? null);

            if($vetoSubdomain) {

                $event->setResponse($this->redirectByReduction(false, true));
                return $event->stopPropagation();
            }
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
