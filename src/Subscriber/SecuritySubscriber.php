<?php

namespace Base\Subscriber;

use Base\Entity\User as BaseUser;
use App\Repository\UserRepository;
use Base\Service\ReferrerInterface;
use App\Entity\User;

use Base\Security\LoginFormAuthenticator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Base\Entity\User\Notification;
use Base\Enum\UserRole;
use Base\Routing\RouterInterface;
use Base\Security\RescueFormAuthenticator;
use Base\Service\Localizer;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\MaternityUnitInterface;

use Symfony\Component\HttpFoundation\RedirectResponse;
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
use Base\Service\SettingBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

class SecuritySubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var AuthorizationChecker
     */
    private $authorizationChecker;

    /**
     * @var MaternityUnitInterface
     */
    private $maternityUnit;

    /**
     * @var MaintenanceProvider
     */
    private $maintenanceProvider;

    /**
     * @var Profiler
     */
    private $profiler;

    /**
     * @var Referrer
     */
    private $referrer;

    /**
     * @var ParameterBag
     */
    private $parameterBag;

    /**
     * @var SettingBag
     */
    private $settingBag;

    /**
     * @var Localizer
     */
    private $localizer;

    /**
     * @var Translator
     */
    private $translator;

    public function __construct(
        UserRepository $userRepository,
        AuthorizationChecker $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        ReferrerInterface $referrer,
        SettingBagInterface $settingBag,
        Localizer $localizer,
        RouterInterface $router,
        ParameterBagInterface $parameterBag,
        MaintenanceProviderInterface $maintenanceProvider,
        MaternityUnitInterface $maternityUnit,
        ?Profiler $profiler = null) {

        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->translator  = $translator;
        $this->router  = $router;

        $this->localizer = $localizer;
        $this->userRepository = $userRepository;

        $this->maternityUnit = $maternityUnit;
        $this->maintenanceProvider = $maintenanceProvider;
        $this->parameterBag = $parameterBag;

        $this->requestStack = $requestStack;
        $this->settingBag = $settingBag;
        $this->referrer = $referrer;
        $this->profiler = $profiler;

    }

    public static function getSubscribedEvents(): array
    {
        return [

            /* referer goes first, because kernelrequest then redirects consequently if user not verified */
            RequestEvent::class      => [['onMaintenanceRequest', 4], ['onBirthRequest', 4], ['onAccessRequest', 6], ['onKernelRequest', 3]],
            ResponseEvent::class     => ['onKernelResponse'],
            LoginSuccessEvent::class => ['onLoginSuccess', -65],
            LoginFailureEvent::class => ['onLoginFailure'],
            LogoutEvent::class       => ['onLogout']
        ];
    }

    public function onAccessRequest(?RequestEvent $event = null): bool
    {
        if(!$this->router->getRouteFirewall()?->isSecurityEnabled()) return true;

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
            if(!$specialGrant && $this->profiler && !$this->router->isDebug())
                $this->profiler->disable();

            // Rescue authenticator must always be public
            $isSecurityRoute = RescueFormAuthenticator::isSecurityRoute($event->getRequest());
            if($isSecurityRoute) return true;

            //
            // Prevent average guy to see the administration and debug tools
            if($this->router->isProfiler() && !$this->authorizationChecker->isGranted("BACKEND"))
                throw new NotFoundHttpException();

            if($this->router->isEasyAdmin() && !$this->authorizationChecker->isGranted("BACKEND"))
            if(!$isSecurityRoute) throw new NotFoundHttpException();

            //
            // Nonetheless exception access is always possible
            // Let's notify connected user that there is a special access grant for this page
            if(!$this->router->isProfiler() && !$this->router->isEasyAdmin() && $this->authorizationChecker->isGranted("EXCEPTION_ACCESS")) {

                if($specialGrant) {

                    $notification = new Notification("access_restricted.".$restrictionType.".exception");
                    $notification->send("info");
                }

                return true;
            }

            //
            // If not, then user is redirected to a specific route
            $routeRestriction = $this->settingBag->getScalar("base.settings.access_restriction.redirect_on_deny") ?? [];
            foreach($routeRestriction as $i => $route)
                $routeRestriction[$i] = str_rstrip($route, ".".$this->localizer->getDefaultLocaleLang());

            if (!in_array($this->router->getRouteName(), $routeRestriction)) {

                if($specialGrant) {

                    // If not let them know that this page is locked for others
                    if($this->authorizationChecker->isGranted("ROLE_SUPERADMIN") && !$this->router->isBackend()) {

                        $notification = new Notification("access_restricted.".$restrictionType.".message");
                        $notification->send("warning");
                    }

                    return true;
                }

                $response   = $routeRestriction ? $this->router->redirect(first($routeRestriction) ?? $this->router->getRoute(RescueFormAuthenticator::PENDING_ROUTE)) : null;
                $response ??= $this->router->redirect(RescueFormAuthenticator::LOGIN_ROUTE);

                if($event) $event->setResponse($response);
                if($event) $event->stopPropagation();

                return false;

            } else if($specialGrant) {

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
        if(!$user instanceof BaseUser) return;

        // Notify user about the authentication method
        $exceptions = $this->parameterBag->get("base.access_restrictions.route_exceptions") ?? [];
        $exceptions = array_merge($exceptions, ["/^(security|user|ux)_(?:.*)$/"]);
        if ($token instanceof SwitchUserToken) {

            $switchParameter = $this->router->getRouteFirewall()->getSwitchUser()["parameter"] ?? "_switch_user";

            $notification = new Notification("impersonator", [$user, $switchParameter]);
            $notification->send("warning");
        }

        if($user->isKicked()) {

            $notification = new Notification("kickout", [$user]);
            $notification->send("warning");

            $this->referrer->setUrl($event->getRequest()->getUri());
            $this->router->redirectEvent($event, LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE);

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

                        $notification = new Notification("verifyEmail.pending", [$this->router->generate("security_verifyEmail")]);
                        $notification->send("warning");
                    }
                };

                $response    = $event->getResponse();
                $alreadyRedirected = $response && $response->getStatusCode() == 302;
                $isException =  $this->router->isEasyAdmin() || $this->router->isProfiler() || !$this->router->isSecured();

                if($alreadyRedirected || $isException) $callbackFn();
                else $this->router->redirectEvent($event, "user_profile", [], 302, [
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

            } else if ($this->router->isSecured()) {

                $this->router->redirectEvent($event, "security_pendingForApproval", [], 302,  ["exceptions" => $exceptions]);
            }

        } else if($this->router->getRouteName() == "security_pendingForApproval") {

            $this->router->redirectEvent($event, $this->router->getRouteIndex(), [], 302,  ["exceptions" => $exceptions]);
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
        if(!$user instanceof BaseUser) return;

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
        $user = $event->getUser();
        if ($user instanceof BaseUser) {

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
            $this->requestStack->getSession()?->set("_user", $user);

        $this->router->redirectEvent($event, LoginFormAuthenticator::LOGOUT_ROUTE, [], 302);
    }

    public function onMaintenanceRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;

        if($this->maintenanceProvider->redirectOnDeny($event, $this->localizer->getLocale())) {
            if($this->profiler) $this->profiler->disable();
            $event->stopPropagation();
        }
    }

    public function onBirthRequest(RequestEvent $event)
    {
        if(!$event->isMainRequest()) return;

        if($this->maternityUnit->redirectOnDeny($event, $this->localizer->getLocale())) {
            if($this->profiler) $this->profiler->disable();
            $event->stopPropagation();
        }
    }
}
