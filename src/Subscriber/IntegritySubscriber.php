<?php

namespace Base\Subscriber;

use Base\Database\Annotation\Vault;
use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Security\RescueFormAuthenticator;
use Base\Service\BaseService;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class IntegritySubscriber implements EventSubscriberInterface
{
    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @Vault
     */
    protected $vault;

    /**
     * @string
     */
    private $secret;

    public function __construct(TokenStorageInterface $tokenStorage, TranslatorInterface $translator, RequestStack $requestStack, ManagerRegistry $doctrine, BaseService $baseService, RouterInterface $router, string $secret = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
        $this->translator   = $translator;
        $this->doctrine     = $doctrine;
        $this->baseService  = $baseService;
        $this->router       = $router;

        $this->secret       = $secret;
        $this->vault        = new Vault();
    }

    public static function getSubscribedEvents(): array
    {
        return
        [
            RequestEvent::class      => [['onKernelRequest', 8]],
            LoginSuccessEvent::class => ['onLoginSuccess', 1],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event)
    {
        $token = $this->tokenStorage->getToken();
        if(!$token) return true;

        $user = $token->getUser();
        if($user === null) return true;

        $session = $this->requestStack->getSession();

        if(!$session->get("_integrity/doctrine"))
            $session->set("_integrity/doctrine", $this->getDoctrineChecksum());
        if(!$session->get("_integrity/secret"))
            $session->set("_integrity/secret", $this->getSecret());
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $token = $this->tokenStorage->getToken();
        if(!$token) return;

        $integrity  = $this->checkUserIntegrity();
        $integrity &= $this->checkSecretIntegrity();
        $integrity &= $this->checkDoctrineIntegrity();

        if(!$integrity) {

            $user = $token->getUser();
            $notification = new Notification("integrity", [$user]);
            $notification->send("danger");

            $this->tokenStorage->setToken(NULL);
            $session = $this->requestStack->getSession();
            $session->remove("_integrity/secret");
            $session->remove("_integrity/doctrine");

            $response = new Response();
            $response->headers->clearCookie('REMEMBERME', "/");
            if(($host = parse_url(format_url(get_url(), FORMAT_URL_NOMACHINE|FORMAT_URL_NOSUBDOMAIN))["host"] ?? null))
                $response->headers->clearCookie('REMEMBERME', "/", ".".$host);

            $response->sendHeaders();

            $response = $this->baseService->redirectToRoute(RescueFormAuthenticator::LOGIN_ROUTE, [], 302);
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
        if(!$host) return "";

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



    public function checkUserIntegrity() {

        $token = $this->tokenStorage->getToken();
        if(!$token) return true;

        /**
         * @var User
         */
        $user = $token->getUser();
        if($user === null) return true;

        $persistentCollection = ($user->getLogs() instanceof PersistentCollection ? (array) $user->getLogs() : null);
        if($persistentCollection === null) return false;

        $dirtyCollection = [
            "\x00Doctrine\ORM\PersistentCollection\x00snapshot" => [],
            "\x00Doctrine\ORM\PersistentCollection\x00owner" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00association" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00em" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00backRefFieldName" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00typeClass" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00isDirty" => false,
            "\x00*\x00initialized" => false
        ];

        return array_intersect_key($persistentCollection, $dirtyCollection) !== $dirtyCollection;
    }

    public function checkDoctrineIntegrity()
    {
        $token = $this->tokenStorage->getToken();
        if(!$token) return true;

        $user = $token->getUser();
        if($user === null) return true;

        $session = $this->requestStack->getSession();
        if (!$session->get("_integrity/doctrine")) return false;

        $checksum = $this->getDoctrineChecksum();
        return $checksum == $session->get("_integrity/doctrine");
    }

    public function checkSecretIntegrity()
    {
        if($this->secret == null) return true;

        $marshaller = $this->vault->getMarshaller();
        $session = $this->requestStack->getSession();
        if (!$session->get("_integrity/secret")) return false;

        return $this->secret == $this->vault->reveal($marshaller, $session->get("_integrity/secret"));
    }
}
