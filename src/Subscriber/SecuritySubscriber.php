<?php

namespace Base\Subscriber;

use Base\Component\HttpFoundation\Referrer;
use Base\Entity\User;

use Base\Service\BaseService;
use Base\Security\LoginFormAuthenticator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Base\EntityEvent\UserEvent;
use Base\Enum\UserRole;
use Base\Security\RescueFormAuthenticator;
use Base\Service\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    public function __construct(
        EntityManagerInterface $entityManager,
        AuthorizationChecker $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        TranslatorInterface $translator,
        BaseService $baseService,
        Referrer $referrer,
        Profiler $profiler,
        ParameterBagInterface $parameterBag) {

        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->translator  = $translator;
        $this->entityManager = $entityManager;
        $this->userRepository = $entityManager->getRepository(User::class);

        $this->baseService = $baseService;
        $this->referrer = $referrer;
        $this->profiler = $profiler;
        $this->parameterBag = $parameterBag;

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
            SwitchUserEvent::class => ['onSwitchUser'],

            /* referer goes first, because kernelrequest then redirects consequently if user not verified */
            RequestEvent::class    => [['onAccessRestriction', 8], ['onKernelRequest', 8], ['onReferrerRequest', 2]],
            ResponseEvent::class   => ['onKernelResponse'],

            LoginSuccessEvent::class => ['onLoginSuccess'],
            LoginFailureEvent::class => ['onLoginFailure'],
            LogoutEvent::class       => ['onLogout'],

            UserEvent::REGISTER => ['onRegistration'],
            UserEvent::APPROVAL => ['onApproval'],
            UserEvent::VERIFIED => ['onVerification'],
            UserEvent::ENABLED  => ['onEnabling'],
            UserEvent::DISABLED => ['onDisabling'],
            UserEvent::KICKED   => ['onKickout']
        ];
    }

    public function onEnabling(UserEvent $event)
    {
        $user = $event->getUser();
        if($this->tokenStorage->getToken()->getUser() != $user) return; // Only notify when user requests itself

        $notification = new Notification("accountWelcomeBack.success", [$user]);
        $notification->setUser($user);

        if($this->tokenStorage->getToken()->getUser() == $user)
            $notification->send("success");
    }

    public function onDisabling(UserEvent $event)
    {
        $user = $event->getUser();
        if($this->tokenStorage->getToken()->getUser() != $user) return; // Only notify when user requests itself

        $notification = new Notification("accountGoodbye.success", [$user]);
        $notification->setUser($user);
        $notification->setHtmlTemplate("@Base/security/email/account_goodbye.html.twig");

            $notification->send("success")->send("email");
    }

    public function onKickout(UserEvent $event) { }

    public function onVerification(UserEvent $event) { }

    public function onRegistration(UserEvent $event)
    {
        $token = $this->tokenStorage->getToken();
        $user = $event->getUser();

        if($token && $token->getUser() != $user) return; // Only notify when user requests itself

        if ($user->isVerified()) { // Social account connection

            $notification = new Notification("verifyEmail.success");
            $notification->send("success");

            $this->userRepository->flush($user);

        } else {

            /**
             * @var \App\Entity\User\Token
             */
            $verifyEmailToken = new Token('verify-email', 3600);
            $user->addToken($verifyEmailToken);

            $notification = new Notification('verifyEmail.check');
            $notification->setUser($user);
            $notification->setHtmlTemplate('@Base/security/email/verify_email.html.twig', ["token" => $verifyEmailToken]);

            $this->userRepository->flush($user);
            $notification->send("email")->send("success");
        }

        $this->baseService->redirectToRoute("user_profile", [], 302);
    }

    public function onApproval(UserEvent $event)
    {
        $user = $event->getUser();

        $adminApprovalToken = $user->getValidToken("admin-approval");
        if ($adminApprovalToken) {

            $adminApprovalToken->revoke();

            $notification = new Notification("adminApproval.approval");
            $notification->setUser($user);
            $notification->setHtmlTemplate("@Base/security/email/admin_approval_confirm.html.twig");
            $notification->send("email");
        }

        $this->userRepository->flush($user);
    }

    public function onSwitchUser(SwitchUserEvent $event) { }

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
        $session->remove('_security.main.target_path');
        $session->remove('_security.account.target_path');

        $currentRouteIsLoginForm = in_array($currentRoute, [
            LoginFormAuthenticator::LOGOUT_ROUTE,
            LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE,
            LoginFormAuthenticator::LOGIN_ROUTE,
            RescueFormAuthenticator::RESCUE_ROUTE]
        );

        $session->set('_target_path', $currentRoute == $targetRoute || $currentRouteIsLoginForm ? $targetPath : null);

        $targetRouteIsLoginForm = in_array($targetRoute, [
            LoginFormAuthenticator::LOGOUT_ROUTE,
            LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE,
            LoginFormAuthenticator::LOGIN_ROUTE,
            RescueFormAuthenticator::RESCUE_ROUTE]
        );

        if ($targetPath && !$targetRouteIsLoginForm)
            return $this->baseService->redirect($targetPath, [], 302);
    }

    public function onAccessRestriction(RequestEvent $event)
    {
        $isSecurityRoute = RescueFormAuthenticator::isSecurityRoute($event->getRequest());
        if($isSecurityRoute) return;

        //
        // Prevent the average guy to see the administration
        if($this->baseService->isEasyAdmin() && !$this->authorizationChecker->isGranted("BACKOFFICE", $event->getRequest()))
            if(!$isSecurityRoute) throw new NotFoundHttpException();

        //
        // Redirect if basic access not granted
        $accessRestricted  = !$this->authorizationChecker->isGranted("PUBLIC_ACCESS");
        $accessRestricted |= !$this->authorizationChecker->isGranted("USER_ACCESS");
        $accessRestricted |= !$this->authorizationChecker->isGranted("ADMIN_ACCESS");
        $accessRestricted |= !$this->authorizationChecker->isGranted("EDITOR_ACCESS");
        $accessRestricted |= !$this->authorizationChecker->isGranted("MAINTENANCE_ACCESS");

        if($accessRestricted) {


            // In case of restriction: profiler is disabled
            $this->profiler->disable();

            // Nonetheless exception access is alway possible
            if($this->authorizationChecker->isGranted("EXCEPTION_ACCESS"))
                return;

            // If not, then user is redirected to a specific route
            $currentRouteName = $this->getCurrentRouteName($event);
            $accessDeniedRedirection = $this->baseService->getSettingBag()->getScalar("base.settings.access_denied_redirection");
            if(!in_array($currentRouteName, [$accessDeniedRedirection, RescueFormAuthenticator::RESCUE_ROUTE, LoginFormAuthenticator::LOGOUT_ROUTE, LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE])) {

                if($accessDeniedRedirection) $this->baseService->redirect($accessDeniedRedirection);
                else {

                    $response = $this->baseService->redirectToRoute(RescueFormAuthenticator::RESCUE_ROUTE);
                    if($response) $event->setResponse($response);
                }

                // User gets disconnected if access not granted
                $token = $this->tokenStorage->getToken();
                if($token) $this->tokenStorage->setToken(NULL);
                return $event->stopPropagation();
            }
        }
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
        if ($this->authorizationChecker->isGranted('IS_IMPERSONATOR')) {

            $notification = new Notification("impersonator", [$user]);
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

        if ($this->authorizationChecker->isGranted(UserRole::ADMIN)) {

            $user->approve();
            $this->userRepository->flush($user);

        } else if($this->baseService->getParameterBag("base.user.autoapprove")) {

            $user->approve();
            $this->userRepository->flush($user);
        }

        //
        // Check if user is verified
        // (NB:exception in debut mode for user matching test_recipient emails)
        if (!$user->isVerified() && !$user->isTester()) {

                $callbackFn = function () use ($user) {

                    $verifyEmailToken = $user->getToken("verify-email");
                    if($verifyEmailToken && $verifyEmailToken->hasVeto()) {

                        $notification = new Notification("verifyEmail.alreadySent", [$verifyEmailToken->getDeadtimeStr()]);
                        $notification->send("info");

                    } else {

                        $notification = new Notification("verifyEmail.pending", [$this->baseService->generateUrl("security_verifyEmail")]);
                        $notification->send("warning");
                    }
                };

                $response    = $event->getResponse();
                $redirection = $response && $response->getStatusCode() == 302;
                if($redirection || $this->baseService->isEasyAdmin() || $this->baseService->isProfiler()) $callbackFn();
                else $this->baseService->redirectToRoute("user_profile", [], 302, ["event" => $event, "exceptions" => $exceptions, "callback" => $callbackFn]);
        }

        if (! $user->isApproved()) {

            $this->baseService->redirectToRoute("user_profile", [], 302, ["event" => $event, "exceptions" => $exceptions, "callback" => function() {

                $notification = new Notification("login.pending");
                $notification->send("warning");
            }]);

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
                if($isAuthenticated) {

                    $active = daydiff($user->isActive()) == -1 ? "first" : "back";

                         if(time_is_between($user->getActiveAt(), "05:00:00", "10:00:00")) $period = "morning";
                    else if(time_is_between($user->getActiveAt(), "12:00:00", "15:00:00")) $period = "afternoon";
                    else if(time_is_between($user->getActiveAt(), "19:00:00", "05:00:00")) $period = "evening";
                    else $period = "day";

                    $title = "@notifications.login.success.$period.$active";

                } else {

                    $title = "@notifications.login.success.alien";
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
}
