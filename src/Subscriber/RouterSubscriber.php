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
        $redirectToFallback  = $this->parameterBag->get("base.router.host_restriction") && !$this->authorizationChecker->isGranted("VALIDATE_HOST", $route);
        $redirectToFallback |= $ipRestriction;
        if ($redirectToFallback) {

            $ipFallback = array_key_exists("ip", parse_url2($this->router->getHostFallback()));
            if (!$this->parameterBag->get("base.router.ip_access") && $ipFallback)
                throw new \LogicException("IP access is disallowed and your fallback is an IP address. Either change your fallback `HTTP_DOMAIN` or turn on `base.router.ip_access`");

            //
            // If host specified in rozute, then check the list of permitted subdomain
            $url = get_url(); // Redirect to proper host fallback if required.
            if ($ipRestriction && $this->router->getHost() && $this->router->getHost() != $this->router->getHostFallback()) {

                $url = parse_url2($url);
                if($ipRestriction || !$this->router->keepDomain()) {

                    $url["host"] = $this->router->getHostFallback();
                    $url["port"] = $this->router->getPortFallback();
                }

                $url = compose_url(
                    $url["scheme"]  ?? null,
                    null,
                    null,
                    null,
                    null,
                    $url["host"] ?? null,
                    $url["port"] ?? null,
                    $url["path"]    ?? null,
                    $url["query"]     ?? null
                );
            }

            // Redirect to sanitized url
            $formattedUrl = $this->router->format($url);
            dump($formattedUrl);
            if($formattedUrl != get_url()) {

                $event->setResponse(new RedirectResponse($formattedUrl));
            }
            return $event->stopPropagation();
        }

        //
        // If no host specified in Route, then check the list of permitted subdomain
        if (!$this->authorizationChecker->isGranted("VALIDATE_PATH", $route)) {
            $event->setResponse(new RedirectResponse(sanitize_url(get_url())));
            return $event->stopPropagation();
        }
    }
}
