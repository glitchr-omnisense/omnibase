<?php

namespace Base\Request;

use Base\Entity\User\Notification;
use Omnibridge\Contract\Access;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * A request rule's decision applied to a Symfony request event by the former services that answered it
 * there (MaintenanceProvider::redirectOnDeny(), Launcher::redirectOnDeny(); deprecated until 6.0, omnibridge/
 * symfony's SecuritySubscriber asks the rules itself): the notices sent, the redirection set on the event,
 * the account signed out. True when the request stops there (the profiler off).
 *
 * @internal for a class holding $router, $authorizationChecker and $tokenStorage
 */
trait EventDecisions
{
    protected function applyDecision(Decision $decision, ?RequestEvent $event): bool
    {
        foreach ($decision->notices as [$key, $parameters, $importance]) {
            (new Notification($key, $parameters))->send($importance);
        }
        if (null !== $event && null !== $decision->route) {
            $this->router->redirectEvent($event, $decision->route, $decision->parameters, $decision->status);
        }
        if ($decision->signOut) {
            $user = match (true) {
                $this->tokenStorage instanceof Access => $this->tokenStorage->getUser(),
                null !== $this->tokenStorage => $this->tokenStorage->getToken()?->getUser(),
                $this->authorizationChecker instanceof Access => $this->authorizationChecker->getUser(),
                default => null,
            };
            if ($user && method_exists($user, 'Logout')) {
                $user->Logout();
            }
        }

        return !$decision->profiler;
    }
}
