<?php

namespace Base\Subscriber;

use Base\Service\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

        $route = $this->router->getRoute();
        if(!$route) return;

        if(empty($route->getHost())) {

            $reduce = !$this->router->keepMachine() || !$this->router->keepSubdomain();
            if($url["machine"] && $reduce) {

                $event->setResponse($this->redirectByReduction(false, true));
                return $event->stopPropagation();
            }

            $vetoSubdomain = true;
            $permittedSubdomains = $this->parameterBag->get("base.http_restriction.permitted_subdomains") ?? [];
            foreach($permittedSubdomains ?? [] as $permittedSubdomain)
                $vetoSubdomain &= !preg_match("/".$permittedSubdomain."/", $url["subdomain"]);

            if($vetoSubdomain) {

                $event->setResponse($this->redirectByReduction(false, true));
                return $event->stopPropagation();
            }
        }
    }

    public function redirectByReduction(bool $keep_subdomain = true, bool $keep_machine = true): RedirectResponse
    {
        return new RedirectResponse(get_url($keep_subdomain, $keep_machine));
    }
}
