<?php

namespace Base\Subscriber;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Base\Entity\User;

use Base\Service\BaseService;
use Base\Entity\User\Log;

use Symfony\Component\HttpKernel\Event\KernelEvent;
use Base\Security\LoginFormAuthenticator;

use Symfony\Component\Config\Definition\Exception\Exception;

use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Base\EntityEvent\UserEvent;
use Base\Repository\User\NotificationRepository;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Core\Security;

use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecuritySubscriber implements EventSubscriberInterface
{
    /**
     * @var BaseService
     */
    private $baseService;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var TraceableEventDispatcher
     */
    private $dispatcher;

    public function __construct(
        Security $security,
        EventDispatcherInterface $dispatcher,
        TranslatorInterface $translator,
        NotificationRepository $notificationRepository,
        BaseService $baseService) {

        $this->security    = $security;
        $this->dispatcher  = ($dispatcher instanceof TraceableEventDispatcher ? $dispatcher : 
                              new TraceableEventDispatcher($dispatcher, new Stopwatch()));

        $this->translator  = $translator;
        $this->baseService = $baseService;

        $this->notificationRepository = $notificationRepository;        
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::TERMINATE => [['onKernelTerminate']],
            KernelEvents::EXCEPTION => [['onKernelException', -1024]],

            LoginSuccessEvent::class => ['onLoginSuccess'],
            LoginFailureEvent::class  => ['onLoginFailure'],
            LogoutEvent::class       => ['onLogout'],

            SwitchUserEvent::class => ['onSwitchUser'],
            RequestEvent::class    => [['onKernelRequest']],

            UserEvent::REGISTER => ['onRegistration'],
            UserEvent::APPROVAL => ['onApproval'],
            UserEvent::VERIFIED => ['onVerification'],
            UserEvent::ENABLED => ['onEnabling'],
            UserEvent::DISABLED => ['onDisabling']
        ];
    }

    public function onEnabling(UserEvent $event)
    {
        $user = $event->getUser();
        if($this->security->getToken()->getUser() != $user) return; // Only notify when user requests itself

        $notification = new Notification("notifications.accountWelcomeBack.success", [$user]);
        $notification->setUser($user);

        if($this->security->getToken()->getUser() == $user)
            $notification->send("success");
    }

    public function onDisabling(UserEvent $event)
    {
        $user = $event->getUser();
        if($this->security->getToken()->getUser() != $user) return; // Only notify when user requests itself

        $notification = new Notification("notifications.accountGoodbye.success", [$user]);
        $notification->setUser($user);
        $notification->setHtmlTemplate("@Base/security/email/account_goodbye.html.twig");

            $notification->send("success")->send("email");
    }

    public function onVerification(UserEvent $event) { }

    public function onRegistration(UserEvent $event)
    {
        $token = $this->security->getToken();
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

            $this->baseService->getDoctrine()->getManager()->flush();
            $notification->send("email")->send("success");
        }

        $this->baseService->redirectToRoute($event, "base_profile");
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

        $this->baseService->getDoctrine()->getManager()->flush();
    }

    public function onSwitchUser(SwitchUserEvent $event)
    {
        $request = $event->getRequest();
        if (!($user = $event->getTargetUser()))
            return;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        //Notify user about the authentication method
        if (!($user = $this->security->getUser())) return;

        if ($this->baseService->isGranted("IS_AUTHENTICATED_FULLY") && $this->baseService->getCurrentRouteName() == LoginFormAuthenticator::LOGIN_ROUTE)
            return $this->baseService->redirectToRoute($event, "base_profile");

        $exceptionList = [
            "/^(app|base)_(verifyEmail(_token)*)$/",
            "/^(app|base)_(resetPassword(_token)*)$/",
            "/^(app|base)_(logout|settings|profile)$/"];

        if (! $user->isVerified()) {

            $this->baseService->redirectToRoute($event, "base_profile", $exceptionList, function() {
                
                $notification = new Notification("notifications.verifyEmail.pending", [$this->baseService->getRoute("base_verifyEmail")]);
                $notification->send("warning");
            });

        } else if (! $user->isApproved()) {

            $this->baseService->redirectToRoute($event, "base_profile", $exceptionList, function() {

                $notification = new Notification("notifications.login.pending");
                $notification->send("warning");
            });
        }

        if ($this->security->isGranted('IS_IMPERSONATOR')) {

            $notification = new Notification("notifications.impersonator", [$user]);
            $notification->send("warning");
        }
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

                if ($this->security->isGranted('IS_AUTHENTICATED_FULLY'))
                    $title = "notifications.login.success.normal";
                else if ($this->security->isGranted('IS_AUTHENTICATED_REMEMBERED'))
                    $title = "notifications.login.success.back";
                else
                    $title = "notifications.login.success.alien";

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

        return $this->baseService->redirectToRoute($event, LoginFormAuthenticator::LOGOUT_ROUTE);
    }

    public function onStoreLog(KernelEvent $event, ?\Throwable $exception = null) {

        if (self::$exceptionOnHold && self::$exceptionOnHold != $exception)
            return;

        if (!$event->isMainRequest()) return;
        $request = $event->getRequest();

        // Monitored listeners
        $monitoredEntries = $this->baseService->getParameterBag("base.logging");
        if(empty($monitoredEntries)) return;

        // Format monitored entries
        foreach ($monitoredEntries as $key => $entry) {

            if (!array_key_exists("event", $monitoredEntries[$key]))
                throw new Exception("Missing key \"event\" in monitored events #" . $key);

            if (!array_key_exists("listener", $monitoredEntries[$key]))
                $monitoredEntries[$key]["listener"] = "*";

            if (!array_key_exists("statusCode", $monitoredEntries[$key]))
                $monitoredEntries[$key]["statusCode"] = "*";

            $monitoredEntries[$key]["listener"] = str_replace("\\", "\\\\", $monitoredEntries[$key]["listener"]);
            $monitoredEntries[$key]["listener"] = trim(ltrim($monitoredEntries[$key]["listener"], '\\'));
            $monitoredEntries[$key]["listener"] = "/" . $monitoredEntries[$key]["listener"] . "/";
            if ($monitoredEntries[$key]["listener"] == "/*/")
                $monitoredEntries[$key]["listener"] = "/.*/";

            $monitoredEntries[$key]["statusCode"] = trim($monitoredEntries[$key]["statusCode"]);
            $monitoredEntries[$key]["statusCode"] = "/" . $monitoredEntries[$key]["statusCode"] . "/";
            if ($monitoredEntries[$key]["statusCode"] == "/*/")
                $monitoredEntries[$key]["statusCode"] = "/.*/";
        }

        // Check called listeners
        $entries = $this->dispatcher->getCalledListeners();
        foreach ($entries as $entry) {

            if (!array_key_exists("event", $entry))
                throw new Exception("Array key \"event\" missing in dispatcher entry");
            if (!array_key_exists("pretty", $entry))
                throw new Exception("Array key \"pretty\" missing in dispatcher entry");

            $event    = $entry["event"];
            $listener = $entry["pretty"];
            foreach ($monitoredEntries as $monitoredEntry) {

                $monitoredEvent      = $monitoredEntry["event"];
                $monitoredListener   = $monitoredEntry["listener"];
                $monitoredStatusCode = $monitoredEntry["statusCode"];
                if ($monitoredEvent != $event)                   continue;

                if($event == "kernel.exception") {

                    // If kernel exception, listener regex is inhibited
                    if ($listener != __CLASS__ . "::onKernelException") continue;

                    // Handle exception
                    if ($exception == null) continue;
                    if ($exception instanceof HttpException) {
                        if (!preg_match($monitoredStatusCode, $exception->getStatusCode())) continue;
                    } else {
                        if (!preg_match($monitoredStatusCode, $exception->getCode())) continue;
                    }

                } else {

                    // Else just check the provided regex
                    if (!preg_match($monitoredListener, $listener)) continue;
                }

                // Entity Manager closed means most likely an exception
                // due within doctrine execution happened
                $entityManager = $this->baseService->getEntityManager(true);
                if (!$entityManager || !$entityManager->isOpen()) return;

                // In the opposite case, we are storing the exception
                $log = new Log($entry, $request);
                $log->setException($exception ?? null);

                $user = $this->security->getUser();
                if($user) $user = $this->baseService->getEntityById(User::class, $user->getId());

                $impersonator = null;
                if ($this->security->getToken() instanceof SwitchUserToken) {

                    $impersonator = $this->security->getToken()->getOriginalToken()->getUser();
                    if($impersonator)
                        $impersonator = $this->baseService->getEntityById(User::class, $impersonator->getId());
                }

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
