<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Service\BaseService;
use Base\Entity\User\Notification;
use Base\Routing\RouterInterface;
use Base\Security\RescueFormAuthenticator;
use Base\Service\LocaleProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class BirthSubscriber implements EventSubscriberInterface
{
    public function __construct(RouterInterface $router, BaseService $baseService, AuthorizationChecker $authorizationChecker, LocaleProvider $localeProvider)
    {
        $this->router = $router;
        $this->baseService = $baseService;
        $this->authorizationChecker = $authorizationChecker;
        $this->localeProvider = $localeProvider;
    }

    public static function getSubscribedEvents(): array
    {
        return [ RequestEvent::class => ['onRequestEvent'] ];
    }

    public function onRequestEvent(RequestEvent $event)
    {
        if( empty( $this->router->getRouteName()) ) return;

        if($this->router->isProfiler() ) return;
        if($this->router->isEasyAdmin()) return;
        if($this->baseService->isBorn()) return;

        dump($this->isAccessRestricted($this->baseService->getUser()));
        if(!$this->isAccessRestricted($this->baseService->getUser())) return;
        if(!$this->router->getRouteFirewall()->isSecurityEnabled()) return;

        if($this->baseService->getUser() && $this->baseService->isGranted("ROLE_EDITOR")) {

            $notification = new Notification("birth.banner");
            $notification->send("warning");
            return;
        }

        if($this->router->getRouteName() != "security_birth") {
            $this->router->redirectToRoute("security_birth", [], 302, ["event" => $event]);
            $event->stopPropagation();
        }
    }

    public function isAccessRestricted(?User $user)
    {
        //
        // Redirect if basic access not granted
        $adminAccess      = $this->authorizationChecker->isGranted("ADMIN_ACCESS");
        $userAccess       = $this->authorizationChecker->isGranted("USER_ACCESS");
        $anonymousAccess  = $this->authorizationChecker->isGranted("ANONYMOUS_ACCESS");
        
        $accessRestricted = !$adminAccess || !$userAccess || !$anonymousAccess;
        if($accessRestricted) {

            //
            // Check for user special grants (based on roles)
            $specialGrant = $this->authorizationChecker->isGranted("ANONYMOUS_ACCESS", $user);
            if(!$specialGrant) $specialGrant = $this->authorizationChecker->isGranted("USER_ACCESS", $user);
            if(!$specialGrant) $specialGrant = $this->authorizationChecker->isGranted("ADMIN_ACCESS", $user);

            if($user != null && $specialGrant) {

                     if(!$adminAccess) $msg = "admin_restriction";
                else if(!$userAccess)  $msg = "user_restriction";
                else $msg = "public_restriction";

                if(!$this->localeProvider->hasChanged()) {

                    $notification = new Notification("access_restricted.".$msg);
                    $notification->send("warning");
                }

                return;
            }

            // In case of restriction: profiler is disabled
            if($this->profiler) $this->profiler->disable();

            // Rescue authenticator must always be public
            $isSecurityRoute = RescueFormAuthenticator::isSecurityRoute($event->getRequest());
            if($isSecurityRoute) return;

            //
            // Prevent average guy to see the administration and debug tools
            if($this->baseService->isProfiler() && !$this->authorizationChecker->isGranted("BACKEND"))
                throw new NotFoundHttpException();

            if($this->baseService->isEasyAdmin() && !$this->authorizationChecker->isGranted("BACKEND"))
                if(!$isSecurityRoute) throw new NotFoundHttpException();

            // Nonetheless exception access is always possible
            if($this->authorizationChecker->isGranted("EXCEPTION_ACCESS"))
                return;

            // If not, then user is redirected to a specific route
            $currentRouteName = $this->get($event);
            $accessDeniedRedirection = $this->baseService->getSettingBag()->getScalar("base.settings.access_denied_redirect");
            if($currentRouteName != $accessDeniedRedirection) {

                $response   = $accessDeniedRedirection ? $this->baseService->redirect($accessDeniedRedirection) : null;
                $response ??= $this->baseService->redirect(RescueFormAuthenticator::LOGIN_ROUTE);

                $event->setResponse($response);
                return $event->stopPropagation();
            }
        }
    }
}
