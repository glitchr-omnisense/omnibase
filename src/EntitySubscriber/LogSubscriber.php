<?php

namespace Base\EntitySubscriber;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Base\Entity\User;

use Base\BaseBundle;
use Base\Service\BaseService;
use Base\Entity\Extension\Log;

use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Base\Service\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\DependencyInjection\Argument\ServiceLocator;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class LogSubscriber implements EventSubscriberInterface
{
    /**
     * @var BaseService
     */
    protected $baseService;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var array[TraceableEventDispatcher]
     */
    protected $dispatchers = [];

    public function __construct(
        ServiceLocator $dispatcherLocator,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager,
        BaseService $baseService,
        ParameterBagInterface $parameterBag) {

        $this->tokenStorage = $tokenStorage;
        
        $this->baseService = $baseService;
        $this->parameterBag = $parameterBag;
        
        if(BaseBundle::hasDoctrine()) {

            $this->userRepository = $entityManager->getRepository(User::class);
            foreach($dispatcherLocator->getProvidedServices() as $dispatcherId => $_) {

                $dispatcher = $dispatcherLocator->get($dispatcherId);
                if (!$dispatcher instanceof TraceableEventDispatcher) continue;

                $this->dispatchers[] = $dispatcherLocator->get($dispatcherId);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TerminateEvent::class  => ['onKernelTerminate'],
            ExceptionEvent::class  => ['onKernelException', -1024],

            LogoutEvent::class       => ['onLogout'],
        ];
    }

    private $loggingOutUser = null;
    public function onLogout(LogoutEvent $event)
    {
        $token = $event->getToken();
        $user = ($token) ? $token->getUser() : null;
        
        // Get back onLogout token information (to be used to store logs)
        $this->loggingOutUser = $user;
    }

    protected function storeLog(KernelEvent $event, ?\Throwable $exception = null) {

        if (self::$exceptionOnHold && self::$exceptionOnHold != $exception)
            return;

        if (!$event->isMainRequest()) return;
        $request = $event->getRequest();

        // Handle security (not mandatory, $user is null if not defined)
        $token = $this->tokenStorage->getToken();
        $user = ($token ? $token->getUser() : $this->loggingOutUser);
        if(!$user) return;

        // Monitored listeners
        $monitoredEntries = $this->parameterBag->get("base.logging") ?? [];
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
                $log->setUser($user);

                $entityManager->persist($log);

                $this->userRepository->flush($user);
            }
        }
    }

    public function onKernelTerminate(TerminateEvent $event)
    {
        if(!$this->baseService->isDebug()) return;
        if(!BaseBundle::hasDoctrine()) return;

        return $this->storeLog($event);
    }

    private static $exceptionOnHold = null;
    public function onKernelException(ExceptionEvent $event)
    {
        if(!$this->baseService->isDebug()) return;
        if(!BaseBundle::hasDoctrine()) return;

        $exception = $event->getThrowable();

        // Initial exception held here, this is in case of nested exceptions..
        // This guard must be set here, otherwise you are going to miss the first exception..
        // In case the initial exception is related to doctrine, entity manager will be closed.
        if(self::$exceptionOnHold)
            throw self::$exceptionOnHold;

        self::$exceptionOnHold = $exception;
        $this->storeLog($event, $exception);
        self::$exceptionOnHold = null;
    }
}
