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

/**
 *
 */
class RouterSubscriber implements EventSubscriberInterface
{
    /**
     * @var RouterInterface
     */
    protected RouterInterface $router;

    /**
     * @var AuthorizationChecker
     */
    protected AuthorizationChecker $authorizationChecker;

    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

    /**
     * @var SettingBagInterface
     */
    protected SettingBagInterface $settingBag;

    public function __construct(AuthorizationChecker $authorizationChecker, RouterInterface $router, ParameterBagInterface $parameterBag, SettingBagInterface $settingBag)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->settingBag = $settingBag;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $this->router->getRoute();
        if (!$route) {
            return;
        }

        $url = get_url();

        $ipRestriction = !$this->parameterBag->get("base.router.ip_access") && $this->authorizationChecker->isGranted("VALIDATE_IP", $route);
        if ($ipRestriction) {

            $ipFallback = array_key_exists("ip", parse_url2($this->router->getHostFallback()));
            if (!$this->parameterBag->get("base.router.ip_access") && $ipFallback) {
                throw new \LogicException("IP access is disallowed and your fallback is an IP address. Either change your fallback `HTTP_DOMAIN` or turn on `base.router.ip_access`");
            }

            $parsedUrl = parse_url2(get_url());
            $parsedUrl["scheme"] = $this->router->getScheme();
            $parsedUrl["host"] = $this->router->getHostFallback();

            $url = compose_url(
                $parsedUrl["scheme"] ?? null,
                null,
                null,
                null,
                null,
                $parsedUrl["host"] ?? null,
                null,
                $parsedUrl["path"] ?? null,
                $parsedUrl["query"] ?? null,
                $parsedUrl["fragment"] ?? null
            );
        } elseif (!$route->getHost() && $this->router->reducesOnFallback()) {
            $parsedUrl = parse_url2(get_url());
            $parsedUrl["scheme"] = $this->router->getScheme();
            $parsedUrl["machine"] = $this->router->getMachine() ?? null;
            $parsedUrl["subdomain"] = $this->router->getSubdomain() ?? null;
            $parsedUrl["domain"] = $this->router->getDomain() ?? null;
            $parsedUrl["port"] = $this->router->getPort() ?? null;

            $url = compose_url(
                $parsedUrl["scheme"] ?? null,
                null,
                null,
                $parsedUrl["machine"] ?? null,
                $parsedUrl["subdomain"] ?? null,
                $parsedUrl["domain"] ?? null,
                $parsedUrl["port"] ?? null,
                $parsedUrl["path"] ?? null,
                $parsedUrl["query"] ?? null,
                $parsedUrl["fragment"] ?? null
            );
        }

        // Redirect to sanitized url
        if ($url != get_url()) {
            $event->setResponse(new RedirectResponse($url));
            $event->stopPropagation();
        }
    }
}
