<?php

namespace Base\Subscriber;

use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Payum\Core\Exception\LogicException;
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

    /**
     * @var AuthorizationChecker
     */
    protected $authorizationChecker;

    /**
     * @var ParameterBag
     */
    protected $parameterBag;

    /**
     * @var SettingBag
     */
    protected $settingBag;

    public function __construct(AuthorizationChecker $authorizationChecker, RouterInterface $router, ParameterBagInterface $parameterBag, SettingBagInterface $settingBag)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->settingBag = $settingBag;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST  => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return ;
        }

        $route = $this->router->getRoute();
        if(!$route) return;

        $ipRestriction = !$this->parameterBag->get("base.router.ip_access")        &&  $this->authorizationChecker->isGranted("VALIDATE_IP", $route);
        if ($ipRestriction) {

            $ipFallback = array_key_exists("ip", parse_url2($this->router->getHostFallback()));
            if (!$this->parameterBag->get("base.router.ip_access") && $ipFallback)
                throw new \LogicException("IP access is disallowed and your fallback is an IP address. Either change your fallback `HTTP_DOMAIN` or turn on `base.router.ip_access`");
        }

        $url = null;
        if($ipRestriction) {


        } else if (!$route->getHost() && $this->router->reducesOnFallback()) {

            $parsedUrl = parse_url2(get_url());
            $parsedUrl["scheme"] = $this->router->getScheme();

            if($ipRestriction) {}
            $machine = $this->router->getMachineFallback();
            if(in_array($this->router->getMachine(), $this->router->getMachineFallbacks()))
                $machine = $this->router->getMachine();
            
            $subdomain = $this->router->getSubdomainFallback();
            if(in_array($this->router->getSubdomain(), $this->router->getSubdomainFallbacks()))
                $subdomain = $this->router->getSubdomain();

            $domain = $this->router->getDomainFallback();
            if(in_array($this->router->getDomain(), $this->router->getDomainFallbacks()))
                $domain = $this->router->getDomain();

            $port = $this->router->getPortFallback();
            if(in_array($this->router->getPort(), $this->router->getPortFallbacks()))
                $port = $this->router->getPort();

            $parsedUrl["host"] = $this->router->getHostFallback();

            $url = compose_url(
                $parsedUrl["scheme"]  ?? null,
                null,
                null,
                $parsedUrl["machine"] ?? null,
                $parsedUrl["subdomain"] ?? null,
                $parsedUrl["domain"] ?? null,
                $parsedUrl["port"] ?? null,
                $parsedUrl["path"]    ?? null,
                $parsedUrl["query"]     ?? null,
                $parsedUrl["fragment"]     ?? null
            );

            // Redirect to sanitized url
            if($url != get_url()) {

                $event->setResponse(new RedirectResponse($url));
                return $event->stopPropagation();
            }
        }
    }
}
