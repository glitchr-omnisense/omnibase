<?php

namespace Base\Subscriber;

use Base\Database\Annotation\Vault;
use App\Entity\User;
use Base\Entity\User as BaseUser;

use Base\Entity\User\Notification;
use Base\Security\RescueFormAuthenticator;
use Base\BaseBundle;
use Base\Routing\RouterInterface;
use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\Persistence\ManagerRegistry;
use ErrorException;
use InvalidArgumentException;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use TypeError;

class IntegritySubscriber implements EventSubscriberInterface
{
    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var Doctrine
     */
    protected $doctrine;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @Vault
     */
    protected $vault;

    /**
     * @string
     */
    private $secret;

    public function __construct(TokenStorageInterface $tokenStorage, TranslatorInterface $translator, RequestStack $requestStack, ManagerRegistry $doctrine, RouterInterface $router, string $secret = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
        $this->translator   = $translator;
        $this->doctrine     = $doctrine;
        $this->router       = $router;

        $this->secret       = $secret;
        $this->vault        = new Vault();
    }

    public static function getSubscribedEvents(): array
    {
        return
        [
            KernelEvents::EXCEPTION  => ['onException'],
            RequestEvent::class      => ['onKernelRequest', 7],
            LoginSuccessEvent::class => ['onLoginSuccess', 1],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event)
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return true;
        }

        $user = $token->getUser();
        if ($user === null) {
            return true;
        }
    }

    public function onException(ExceptionEvent $event)
    {
        $throwable = $event->getThrowable();
        $instanceOf = ($throwable instanceof TypeError || $throwable instanceof DoctrineException ||
                       $throwable instanceof ErrorException || $throwable instanceof InvalidArgumentException ||
                       $throwable instanceof EntityNotFoundException);

        if ($instanceOf && check_backtrace("Doctrine", "UnitOfWork", $throwable->getTrace())) {
            throw new \RuntimeException("Application integrity compromised, maybe cache needs to be refreshed ?", 0, $throwable);
        }
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (BaseBundle::getInstance()->isBroken() && $event->isMainRequest()) {
            throw new \RuntimeException("Application integrity compromised, maybe cache needs to be refreshed ?");
        }

        $token = $this->tokenStorage->getToken();

        $session = $this->requestStack->getSession();
        if (!$session->get("_integrity/doctrine")) {
            $session->set("_integrity/doctrine", $this->getDoctrineChecksum());
        }
        if (!$session->get("_integrity/secret")) {
            $session->set("_integrity/secret", $this->getSecret());
        }

        if (!$this->router->hasFirewall()) {
            return;
        }
        if ($this->router->getRouteName() == RescueFormAuthenticator::LOGIN_ROUTE) {
            return;
        }

        $integrity  = $this->checkUserIntegrity();
        $integrity &= $this->checkSecretIntegrity();
        $integrity &= $this->checkDoctrineIntegrity();

        if (!$integrity) {
            if ($token) {
                $user = $token->getUser();
                $notification = new Notification("integrity", [$user]);
                $notification->send("danger");
            }

            $this->tokenStorage->setToken(null);
            $session = $this->requestStack->getSession();
            $session->remove("_integrity/secret");
            $session->remove("_integrity/doctrine");

            $response = new Response();
            $response->headers->clearCookie('REMEMBERME', "/");
            if (($host = parse_url(format_url(get_url(), FORMAT_URL_NOMACHINE|FORMAT_URL_NOSUBDOMAIN))["host"] ?? null)) {
                $response->headers->clearCookie('REMEMBERME', "/", ".".$host);
            }

            $response->sendHeaders();

            $response = $this->router->redirectToRoute(RescueFormAuthenticator::LOGIN_ROUTE, [], 302);
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }


    protected function getSecret()
    {
        $marshaller = $this->vault->getMarshaller();
        return $this->vault->seal($marshaller, $this->secret);
    }

    protected function getDoctrineChecksum()
    {
        $connection = $this->doctrine->getConnection($this->doctrine->getDefaultConnectionName());
        $params = $connection->getParams();

        $host = $params["host"] ?? "";
        if (!$host) {
            return "";
        }

        $driver = $params["driver"] ?? null;
        $driver = $driver ? $driver."://" : "";

        $user = $params["user"] ?? null;
        $user = $user ? $user."@" : "";

        $port = $params["port"] ?? null;
        $port = $port ? ":".$port : "";

        $dbname = $params["dbname"] ?? null;
        $dbname = $dbname ? "/".$dbname : "";

        $charset = $params["charset"] ?? null;
        $charset = $charset ? " (".$params["charset"].")" : "";

        return md5($driver.$user.$host.$port.$dbname.$charset);
    }



    public function checkUserIntegrity()
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return true;
        }

        /**
         * @var User
         */
        $user = $token->getUser();
        if ($user === null) {
            return true;
        }
        if (!$user instanceof BaseUser) {
            return true;
        }

        $persistentCollection = ($user->getLogs() instanceof PersistentCollection ? (array) $user->getLogs() : null);
        if ($persistentCollection === null) {
            return false;
        }

        $dirtyCollection = [
            "\x00*\x00initialized" => false,
            "\x00Doctrine\ORM\PersistentCollection\x00snapshot" => [],
            "\x00Doctrine\ORM\PersistentCollection\x00owner" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00association" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00em" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00backRefFieldName" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00typeClass" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00isDirty" => false,
        ];

        return array_intersect_key($persistentCollection, $dirtyCollection) !== $dirtyCollection;
    }

    public function checkDoctrineIntegrity()
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return true;
        }

        $user = $token->getUser();
        if ($user === null) {
            return true;
        }

        $session = $this->requestStack->getSession();
        if (!$session->get("_integrity/doctrine")) {
            return false;
        }

        return $this->getDoctrineChecksum() == $session->get("_integrity/doctrine");
    }

    public function checkSecretIntegrity()
    {
        if ($this->secret == null) {
            return true;
        }

        $marshaller = $this->vault->getMarshaller();
        if ($marshaller == null) {
            return true;
        }

        $session = $this->requestStack->getSession();
        if (!$session->get("_integrity/secret")) {
            return false;
        }

        return $this->secret == $this->vault->reveal($marshaller, $session->get("_integrity/secret"));
    }
}
