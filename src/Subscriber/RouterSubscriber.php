<?php

namespace Base\Subscriber;

use Base\Service\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class RouterSubscriber implements EventSubscriberInterface
{
    /**
     * @var Router
     */
    protected $router;

    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag)
    {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST  => ['onKernelRequest', 256]];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $url = parse_url2(get_url());

        $route = $this->router->getCurrentRoute();
        if(!$route) return;

        $route->addDefaults($url);

        if(empty($route->getHost())) {

            if($url["machine"]) {

                $event->setResponse($this->redirectToSubdomain($url["machine"]));
                return $event->stopPropagation();
            }
            
            $permittedSubdomains = $this->parameterBag->get("base.access_restriction.permitted_subdomains") ?? [];
            
            $vetoSubdomain = true;
            foreach($permittedSubdomains ?? [] as $permittedSubdomain)
                $vetoSubdomain &= preg_match("/".$permittedSubdomain."/", $url["subdomain"]);

            if($vetoSubdomain) {
                $event->setResponse($this->redirectToSubdomain(implode(".", array_filter([$url["machine"], $url["subdomain"]]))));
                return $event->stopPropagation();
            }
        }
    }

    public function redirectToSubdomain(string $subdomain): RedirectResponse
    {
        return new RedirectResponse(get_url($subdomain));
    }
}
