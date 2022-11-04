<?php

namespace Base\Subscriber;

use App\Repository\UserRepository;
use Base\Service\ReferrerInterface;
use Base\Entity\User;

use Base\Service\BaseService;
use Base\Security\LoginFormAuthenticator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Base\Entity\User\Notification;
use Base\Enum\UserRole;
use Base\Routing\RouterInterface;
use Base\Security\RescueFormAuthenticator;
use Base\Service\LocaleProvider;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\MaternityServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

use Base\Service\ParameterBagInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

class SecuritySubscriber implements EventSubscriberInterface
{
    /**
     * @var BaseService
     */
    private $baseService;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        AuthorizationChecker $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        TranslatorInterface $translator,
        BaseService $baseService,
        LocaleProvider $localeProvider,
        ReferrerInterface $referrer,
        RouterInterface $router,
        ParameterBagInterface $parameterBag,
        MaintenanceProviderInterface $maintenanceProvider,
        MaternityServiceInterface $maternityService,
        ?Profiler $profiler = null) {

        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->translator  = $translator;
        $this->router  = $router;

        $this->localeProvider = $localeProvider;
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;

        $this->maternityService = $maternityService;
        $this->maintenanceProvider = $maintenanceProvider;
        $this->parameterBag = $parameterBag;

        $this->baseService = $baseService;
        $this->referrer = $referrer;
        $this->profiler = $profiler;
        $this->exceptions = [
            "/^locale_/",
            "/^ux_/",
            "/^user(?:.*)$/",
            "/^security(?:.*)$/",
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [

            /* referer goes first, because kernelrequest then redirects consequently if user not verified */
            RequestEvent::class    => [
                ['onMaintenanceRequest', 4], ['onBirthRequest', 4], ['onAccessRequest', 6],
                ['onReferrerRequest', 3], ['onKernelRequest', 3],
            ],

            ResponseEvent::class   => ['onKernelResponse'],
            LoginSuccessEvent::class => ['onLoginSuccess'],
            LoginFailureEvent::class => ['onLoginFailure'],
            LogoutEvent::class       => ['onLogout'],
        ];
    }

    public function getCurrentRouteName($event) { return $event->getRequest()->get('_route'); }

    public function isException($route)
    {
        $exceptions = is_string($this->exceptions) ? [$this->exceptions] : $this->exceptions;
        foreach($exceptions as $pattern)
            if (preg_match($pattern, $route)) return true;

        return false;
    }

    public function onReferrerRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;
        if($this->baseService->isProfiler()) return;

        $targetPath = strval($this->referrer);
        $targetRoute = $this->baseService->getRouteName($targetPath);

        $currentRoute = $this->getCurrentRouteName($event);
        if($this->isException($currentRoute)) return;

        $session = $event->getRequest()->getSession();
        $session->remove('_security.main.target_path');    // Internal definition by firewall
        $session->remove('_security.account.target_path'); // Internal definition by firewall

        $currentRouteIsLoginForm = in_array($currentRoute, [
            LoginFormAuthenticator::LOGOUT_ROUTE,
            LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE,
            LoginFormAuthenticator::LOGIN_ROUTE,
            RescueFormAuthenticator::LOGIN_ROUTE]
        );

        $session->set('_target_path', $currentRoute == $targetRoute || $currentRouteIsLoginForm ? $targetPath : null);

        $targetRouteIsLoginForm = in_array($targetRoute, [
            LoginFormAuthenticator::LOGOUT_ROUTE,
            LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE,
            LoginFormAuthenticator::LOGIN_ROUTE,
            RescueFormAuthenticator::LOGIN_ROUTE]
        );

        if ($targetPath && !$targetRouteIsLoginForm)
            return $this->baseService->redirect($targetPath, [], 302);
    }

    public function onAccessRequest(?RequestEvent $event = null): bool
    {
        if(!$this->router->getRouteFirewall()->isSecurityEnabled()) return true;

        if(!$event->isMainRequest()) return true;
        if( $this->router->isWdt($event) ) return true; // Special case for _wdt

        $token = $this->tokenStorage->getToken();
        $user = $token ? $token->getUser() : null;

        //
        // Redirect if basic access not granted
        $adminAccess      = $this->authorizationChecker->isGranted("ADMIN_ACCESS");
        $userAccess       = $this->authorizationChecker->isGranted("USER_ACCESS");
        $anonymousAccess  = $this->authorizationChecker->isGranted("ANONYMOUS_ACCESS");
        
        $accessRestricted = !$adminAccess || !$userAccess || !$anonymousAccess;
        if($accessRestricted) {

                 if(!$adminAccess) $restrictionType = "admin_restriction";
            else if(!$userAccess)  $restrictionType = "user_restriction";
            else $restrictionType = "public_restriction";

            //
            // Check for user special grants (based on roles)
            $specialGrant = $this->authorizationChecker->isGranted("ANONYMOUS_ACCESS", $user);
            if($user && !$specialGrant) $specialGrant = $this->authorizationChecker->isGranted("USER_ACCESS", $user);
            if($user && !$specialGrant) $specialGrant = $this->authorizationChecker->isGranted("ADMIN_ACCESS", $user);
           
            // In case of restriction: profiler is disabled
            if(!$specialGrant && $this->profiler) $this->profiler->disable();

            // Rescue authenticator must always be public
            $isSecurityRoute = RescueFormAuthenticator::isSecurityRoute($event->getRequest());
            if($isSecurityRoute) return true;

            //
            // Prevent average guy to see the administration and debug tools
            if($this->baseService->isProfiler() && !$this->authorizationChecker->isGranted("BACKEND"))
                throw new NotFoundHttpException();

            if($this->baseService->isEasyAdmin() && !$this->authorizationChecker->isGranted("BACKEND"))
            if(!$isSecurityRoute) throw new NotFoundHttpException();

            //
            // Nonetheless exception access is always possible
            // Let's notify connected user that there is a special access grant for this page
            if(!$this->baseService->isProfiler() && !$this->baseService->isEasyAdmin() && $this->authorizationChecker->isGranted("EXCEPTION_ACCESS")) {

                if($user && $specialGrant) {

                    $notification = new Notification("access_restricted.".$restrictionType.".exception");
                    $notification->send("info");
                }

                return true;
            }

            //
            // If not, then user is redirected to a specific route
            $routeRestriction = $this->baseService->getSettingBag()->getScalar("base.settings.access_restriction.redirect_on_deny") ?? [];
            foreach($routeRestriction as $i => $route)
                $routeRestriction[$i] = str_rstrip($route, ".".$this->localeProvider->getDefaultLang());

            if (!in_array($this->router->getRouteName(), $routeRestriction)) {

                if($user && $specialGrant) {

                    // If not let them know that this page is locked for others
                    $notification = new Notification("access_restricted.".$restrictionType.".message");
                    $notification->send("warning");

                    return true;
                }

                $response   = $routeRestriction ? $this->baseService->redirect(first($routeRestriction)) : null;
                $response ??= $this->baseService->redirect(RescueFormAuthenticator::LOGIN_ROUTE);

                if($event) $event->setResponse($response);
                if($event) $event->stopPropagation();

                return false;

            } else if($user && $specialGrant) {

                // If not let them know that this page is locked for others
                $notification = new Notification("access_restricted.".$restrictionType.".on_deny");
                $notification->send("info");

                return true;
            }
        }

        return true;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $token = $this->tokenStorage->getToken();

        /**
         * @var User
         */
        $user = $token ? $token->getUser() : null;
        if(!$user) return;

        // Notify user about the authentication method
        $exceptions = array_merge($this->exceptions, ["/^(?:app|base)_user(?:.*)$/"]);
        if ($token instanceof SwitchUserToken) {

            $switchParameter = $this->router->getRouteFirewall()->getSwitchUser()["parameter"] ?? "_switch_user";

            $notification = new Notification("impersonator", [$user, $switchParameter]);
            $notification->send("warning");
        }

        if($user->isKicked()) {

            $notification = new Notification("kickout", [$user]);
            $notification->send("warning");

            $this->referrer->setUrl($event->getRequest()->getUri());
            $event->setResponse($this->baseService->redirectToRoute(LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE));

            $this->userRepository->flush($user);
            return $event->stopPropagation();
        }

        //
        // Check if user is verified
        // (NB:exception in debut mode for user matching test_recipient emails)
        if (!$user->isVerified() && !$user->isTester() && !$this->router->isSecured()) {

                $callbackFn = function () use ($user) {

                    $verifyEmailToken = $user->getToken("verify-email");
                    if($verifyEmailToken && $verifyEmailToken->hasVeto()) {

                        $notification = new Notification("verifyEmail.alreadySent", [$verifyEmailToken->getThrottleTimeStr()]);
                        $notification->send("info");

                    } else {

                        $notification = new Notification("verifyEmail.pending", [$this->baseService->generateUrl("security_verifyEmail")]);
                        $notification->send("warning");
                    }
                };

                $response    = $event->getResponse();
                $alreadyRedirected = $response && $response->getStatusCode() == 302;
                $isException =  $this->baseService->isEasyAdmin() || $this->baseService->isProfiler();
                
                if($alreadyRedirected || $isException) $callbackFn();
                else $this->baseService->redirectToRoute("user_profile", [], 302, [
                    "event" => $event, 
                    "exceptions" => $exceptions, 
                    "callback" => $callbackFn
                ]);

        }

        if(!$user->isApproved()) {

            if ($this->authorizationChecker->isGranted(UserRole::ADMIN)) {

                $user->approve();
                $this->userRepository->flush($user);

            } else if($this->parameterBag->get("base.user.autoapprove")) {

                $user->approve();
                $this->userRepository->flush($user);

            } else {

                $this->baseService->redirectToRoute("user_profile", [], 302, ["event" => $event, "exceptions" => $exceptions, "callback" => function() {

                    $notification = new Notification("login.pending");
                    $notification->send("warning");
                }]);
            }
        }
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        //Notify user about the authentication method
        if(!($token = $this->tokenStorage->getToken()) ) return;

        /**
         * @var User
         */
        if(!($user = $token->getUser())) return;

        if ( !($user->isActive()) ) {

            $user->poke(new \DateTime("now"));
            $this->userRepository->flush($user);
        }
    }

    public function onLoginFailure(LoginFailureEvent $event)
    {
        $message = "@notifications.login.failed";
        $importance = "danger";
        $data = [];

        if( ($exception = $event->getException()) ) {

            $importance = $exception->getMessageData()["importance"] ?? $importance;
            $data = $exception->getMessageData();

            $message = $this->translator->trans($exception->getMessageKey() ?? $message, $data, "security");
        }

        $notification = new Notification($message, $data);
        $notification->send($importance);
    }

    public function onLoginSuccess(LoginSuccessEvent $event)
    {
        /**
         * @var User
         */
        if ($user = $event->getUser()) {

            if (!$user->isPersistent()) {

                $notification = new Notification("login.social", [$user]);
                $notification->send("success");

            } else if($user->isVerified()) {

                $isAuthenticated = $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY') || $this->authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED');
                if(!$isAuthenticated) $title = "@notifications.login.success.alien";
                else {

                    $active = daydiff($user->getActiveAt()) < 0 ? "first" : "back";

                         if(time_is_between($user->getActiveAt(), "05:00:00", "10:00:00")) $period = "morning";
                    else if(time_is_between($user->getActiveAt(), "12:00:00", "15:00:00")) $period = "afternoon";
                    else if(time_is_between($user->getActiveAt(), "19:00:00", "05:00:00")) $period = "evening";
                    else $period = "day";

                    $title = "@notifications.login.success.$period.$active";
                }

                $notification = new Notification($title, [$user]);
                $notification->send("success");
            }
        }
    }

    public function onLogout(LogoutEvent $event)
    {
        $token = $event->getToken();
        $user = ($token) ? $token->getUser() : null;

        if ($user instanceof User) // Just to remember username.. after logout & first redirection
            $this->baseService->addSession("_user", $user);

        return $this->baseService->redirectToRoute(LoginFormAuthenticator::LOGOUT_ROUTE, [], 302, ["event" => $event]);
    }

    public function onMaintenanceRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;

        if($this->maintenanceProvider->redirectOnDeny($event, $this->localeProvider->getLocale())) {
            if($this->profiler) $this->profiler->disable();
            $event->stopPropagation();
        }
    }

    public function onBirthRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;

        if($this->maternityService->redirectOnDeny($event, $this->localeProvider->getLocale())) {
            if($this->profiler) $this->profiler->disable();
            $event->stopPropagation();
        }
    }
}
