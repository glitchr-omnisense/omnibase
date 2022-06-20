<?php

namespace Base\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class RescueFormAuthenticator extends LoginFormAuthenticator
{
    public const RESCUE_ROUTE   = 'security_rescue';

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate(static::RESCUE_ROUTE));
    }

    public static function isSecurityRoute(Request|string $routeOrRequest)
    {
        return in_array(is_string($routeOrRequest) ? $routeOrRequest : $routeOrRequest->attributes->get('_route'), [
            self::RESCUE_ROUTE,
            self::LOGOUT_ROUTE,
            self::LOGOUT_REQUEST_ROUTE
        ]);
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') == static::RESCUE_ROUTE && $request->isMethod('POST');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $isRescueRoute = $request->attributes->get('_route') == static::RESCUE_ROUTE;
        $loginRoute = $this->router->generate($isRescueRoute ? static::RESCUE_ROUTE : static::LOGIN_ROUTE);

        return new RedirectResponse($loginRoute);
    }
}
