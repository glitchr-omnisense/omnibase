<?php

namespace Base\Subscriber;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Base\Entity\User;

use Base\Service\BaseService;
use Base\Entity\User\Log;

use Symfony\Component\HttpKernel\Event\KernelEvent;
use Base\Security\LoginFormAuthenticator;

use Symfony\Component\Config\Definition\Exception\Exception;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Base\EntityEvent\UserEvent;
use Base\Enum\UserRole;
use Symfony\Component\DependencyInjection\Argument\ServiceLocator;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\HttpKernel\KernelEvents;
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

    /**
     * @var array[TraceableEventDispatcher]
     */
    private $dispatchers = [];

    public function __construct(
        AuthorizationChecker $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        ServiceLocator $dispatcherLocator,
        TranslatorInterface $translator,
        BaseService $baseService) {

        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->translator  = $translator;
        $this->baseService = $baseService;
    
        foreach($dispatcherLocator->getProvidedServices() as $dispatcherId => $_) {

            $dispatcher = $dispatcherLocator->get($dispatcherId);
            if (!$dispatcher instanceof TraceableEventDispatcher) continue;

            $this->dispatchers[] = $dispatcherLocator->get($dispatcherId);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => [['onKernelTerminate']],
            KernelEvents::EXCEPTION => [['onKernelException', -1024]],

            LoginSuccessEvent::class => ['onLoginSuccess'],
            LoginFailureEvent::class => ['onLoginFailure'],
            LogoutEvent::class       => ['onLogout'],

            SwitchUserEvent::class => ['onSwitchUser'],
            RequestEvent::class    => [['onKernelRequest']],

            UserEvent::REGISTER => ['onRegistration'],
            UserEvent::APPROVAL => ['onApproval'],
            UserEvent::VERIFIED => ['onVerification'],
            UserEvent::ENABLED  => ['onEnabling'],
            UserEvent::DISABLED => ['onDisabling']
        ];
    }

    public function onEnabling(UserEvent $event)
    {
        $user = $event->getUser();
        if($this->tokenStorage->getToken()->getUser() != $user) return; // Only notify when user requests itself

        $notification = new Notification("notifications.accountWelcomeBack.success", [$user]);
        $notification->setUser($user);

        if($this->tokenStorage->getToken()->getUser() == $user)
            $notification->send("success");
    }

    public function onDisabling(UserEvent $event)
    {
        $user = $event->getUser();
        if($this->tokenStorage->getToken()->getUser() != $user) return; // Only notify when user requests itself

        $notification = new Notification("notifications.accountGoodbye.success", [$user]);
        $notification->setUser($user);
        $notification->setHtmlTemplate("@Base/security/email/account_goodbye.html.twig");

            $notification->send("success")->send("email");
    }

    public function onVerification(UserEvent $event) { }

    public function onRegistration(UserEvent $event)
    {
        $token = $this->tokenStorage->getToken();
        $user = $event->getUser();
        if($token && $token->getUser() != $user) return; // Only notify when user requests itself

        if ($user->isVerified()) { // Social account connection

            $notification = new Notification("notifications.verifyEmail.success");
            $notification->send("success");

        } else {

            $verifyEmailToken = new Token('verify-email', 3600);
            $user->addToken($verifyEmailToken);

            $notification = new Notification('notifications.verifyEmail.check');
            $notification->setUser($user);
            $notification->setHtmlTemplate('@Base/security/email/verify_email.html.twig', ["token" => $verifyEmailToken]);

            $this->baseService->getEntityManager()->flush();
            $notification->send("email")->send("success");
        }

        $this->baseService->getEntityManager()->flush();
        $this->baseService->redirectToRoute("base_profile", [], $event);
    }

    public function onApproval(UserEvent $event)
    {
        $user = $event->getUser();
        $user->approve();

        $adminApprovalToken = $user->getValidToken("admin-approval");
        if ($adminApprovalToken) {

            $adminApprovalToken->revoke();

            $notification = new Notification("notifications.adminApproval.approval");
            $notification->setUser($user);
            $notification->setHtmlTemplate("@Base/security/email/admin_approval_confirm.html.twig");
            $notification->send("email");
        }

        $this->baseService->getEntityManager()->flush();
    }

    public function onSwitchUser(SwitchUserEvent $event)
    {
        $request = $event->getRequest();
        if (!($user = $event->getTargetUser()))
            return;

        // More..
    }

    public function onKernelRequest(RequestEvent $event)
    {
        //Notify user about the authentication method
        if(!($token = $this->tokenStorage->getToken()) ) return;
        if(!($user = $token->getUser())) return;

        $exceptionList = [
            "/^(app|base)_(verifyEmail(_token)*)$/",
            "/^(app|base)_(resetPassword(_token)*)$/",
            "/^(app|base)_(logout|settings|profile)$/"];

        if (! $user->isVerified()) {

            $this->baseService->redirectToRoute("base_profile", [], $event, $exceptionList, function() {

                $notification = new Notification("notifications.verifyEmail.pending", [$this->baseService->getUrl("base_verifyEmail")]);
                $notification->send("warning");
            });

        } else {

            // Auto approve if administrator at login
            if ($this->authorizationChecker->isGranted(UserRole::ADMIN)) $user->approve();
            else if($this->baseService->getParameterBag("base.user.autoapprove"))

            // If not approved force redirection
            if (! $user->isApproved()) {

                $this->baseService->redirectToRoute("base_profile", [], $event, $exceptionList, function() {

                    $notification = new Notification("notifications.login.pending");
                    $notification->send("warning");
                });
            }
        }

        if ($this->authorizationChecker->isGranted('IS_IMPERSONATOR')) {

            $notification = new Notification("notifications.impersonator", [$user]);
            $notification->send("warning");
        }

        $this->baseService->getEntityManager()->flush();
    }

    public function onLoginFailure(LoginFailureEvent $event)
    {
        $message = "notifications.login.failed";
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
        // Notify user about the authentication method
        if ($user = $event->getUser()) {

            if (!$user->isPersistent()) {

                $notification = new Notification("notifications.login.social", [$user]);
                $notification->send("success");

            } else if($user->isVerified()) {

                if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY'))
                    $title = "notifications.login.success.normal";
                else if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED'))
                    $title = "notifications.login.success.back";
                else
                    $title = "notifications.login.success.alien";

                $notification = new Notification($title, [$user]);
                $notification->send("success");
            }
        }
    }

    private $_onLogoutUser = null;
    private $_onLogoutImpersonator = null;
    public function onLogout(LogoutEvent $event)
    {
        $token = $event->getToken();
        $user = ($token) ? $token->getUser() : null;
        $impersonator = ($token instanceof SwitchUserToken ? $token->getOriginalToken()->getUser() : null);

        if ($user instanceof User) // Just to remember username.. after logout & first redirection
            $this->baseService->addSession("_user", $user);

        // Get back onLogout token information (to be used to store logs)
        $this->_onLogoutUser = $user;
        $this->_onLogoutImpersonator = $impersonator;

        return $this->baseService->redirectToRoute(LoginFormAuthenticator::LOGOUT_ROUTE, [], $event);
    }

    public function onStoreLog(KernelEvent $event, ?\Throwable $exception = null) {

        if (self::$exceptionOnHold && self::$exceptionOnHold != $exception)
            return;

        if (!$event->isMainRequest()) return;
        $request = $event->getRequest();

        // Handle security (not mandatory, $user is null if not defined)
        $token = $this->tokenStorage->getToken();
        $impersonator = ($token instanceof SwitchUserToken ? $token->getOriginalToken()->getUser() : $this->_onLogoutImpersonator);
        $user = ($token ? $token->getUser() : $this->_onLogoutUser);
        if(!$user) return;

        // Monitored listeners
        $monitoredEntries = $this->baseService->getParameterBag("base.logging") ?? [];
        if(!$monitoredEntries) return;

        // Format monitored entries
        foreach ($monitoredEntries as $key => $entry) {

            if (!array_key_exists("event", $monitoredEntries[$key]))
                throw new Exception("Missing key \"event\" in monitored events #" . $key);
            if (!array_key_exists("pretty", $monitoredEntries[$key]))
                $monitoredEntries[$key]["pretty"] = "*";
            if (!array_key_exists("statusCode", $monitoredEntries[$key]))
                $monitoredEntries[$key]["statusCode"] = "*";

            $monitoredEntries[$key]["pretty"] = str_replace("\\", "\\\\", $monitoredEntries[$key]["pretty"]);
            $monitoredEntries[$key]["pretty"] = trim(ltrim($monitoredEntries[$key]["pretty"], '\\'));
            $monitoredEntries[$key]["pretty"] = "/" . $monitoredEntries[$key]["pretty"] . "/";
            if ($monitoredEntries[$key]["pretty"] == "/*/")
                $monitoredEntries[$key]["pretty"] = "/.*/";

            $monitoredEntries[$key]["statusCode"] = trim($monitoredEntries[$key]["statusCode"]);
            $monitoredEntries[$key]["statusCode"] = "/" . $monitoredEntries[$key]["statusCode"] . "/";
            if ($monitoredEntries[$key]["statusCode"] == "/*/")
                $monitoredEntries[$key]["statusCode"] = "/.*/";
        }

        // Check called listeners
        $calledListeners = [];
        foreach($this->dispatchers as $dispatcher)
            $calledListeners = array_merge($calledListeners, $dispatcher->getCalledListeners());

        foreach ($calledListeners as $listener) {

            if (!array_key_exists("event", $listener))
                throw new Exception("Array key \"event\" missing in dispatcher listener");
            if (!array_key_exists("pretty", $listener))
                throw new Exception("Array key \"pretty\" missing in dispatcher listener");

            $event  = $listener["event"];
            $pretty = $listener["pretty"];
            
            foreach ($monitoredEntries as $monitoredEntry) {

                $monitoredStatusCode = $monitoredEntry["statusCode"];
                $monitoredPretty   = $monitoredEntry["pretty"];
                $monitoredEvent      = $monitoredEntry["event"];
                if ($monitoredEvent != $event)                   continue;

                if($event == "kernel.exception") {

                    // If kernel exception, listener regex is inhibited
                    if ($pretty != __CLASS__ . "::onKernelException") continue;

                    // Handle exception
                    if ($exception == null) continue;

                    if ($exception instanceof HttpException && !preg_match($monitoredStatusCode, $exception->getStatusCode())) continue;
                    else if (!preg_match($monitoredStatusCode, $exception->getCode())) continue;

                } else if (!preg_match($monitoredPretty, $pretty)) continue; // Else just check the provided regex

                // Entity Manager closed means most likely an exception
                // due within doctrine execution happened
                $entityManager = $this->baseService->getEntityManager(true);
                if (!$entityManager || !$entityManager->isOpen()) return;

                // In the opposite case, we are storing the exception
                $log = new Log($listener, $request);
                $log->setException($exception ?? null);
                $log->setImpersonator($impersonator);
                $log->setUser($user);

                $entityManager->persist($log);
                $entityManager->flush();
            }
        }
    }

    public function onKernelTerminate(TerminateEvent $event)
    {
        return $this->onStoreLog($event);
    }

    private static $exceptionOnHold = null;
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        // Initial exception held here, this is in case of nested exceptions..
        // This guard must be set here, otherwise you are going to miss the first exception..
        // In case the initial exception is related to doctrine, entity manager will be closed.
        if(self::$exceptionOnHold)
            throw self::$exceptionOnHold;

        self::$exceptionOnHold = $exception;
        $this->onStoreLog($event, $exception);
        self::$exceptionOnHold = null;
    }
}
