<?php

namespace Base\Subscriber;

use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Security\LoginFormAuthenticator;
use Doctrine\DBAL\Connection;
use Base\Service\BaseService;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator;
use Symfony\Component\Routing\RouterInterface;

class IntegritySubscriber implements EventSubscriberInterface
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage, TranslatorInterface $translator, RequestStack $requestStack, ManagerRegistry $doctrine, BaseService $baseService, RouterInterface $router)
    {
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
        $this->translator   = $translator;
        $this->doctrine     = $doctrine;
        $this->baseService  = $baseService;
        $this->router  = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return
        [
            RequestEvent::class      => [['onKernelRequest', 8]],
            LoginSuccessEvent::class => ['onLoginSuccess'],
        ];
    }

    private function getDefaultConnectionStr()
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

        return $driver.$user.$host.$port.$dbname.$charset;
    }

    public function onLoginSuccess(LoginSuccessEvent $event)
    {
        $token = $this->tokenStorage->getToken();
        if(!$token) return true;

        $user = $token->getUser();
        if($user === null) return true;

        $session = $this->requestStack->getSession();
        if(!$session->get("_user_checksum"))
            $session->set("_user_checksum", md5($this->getDefaultConnectionStr()));
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $integrity = $this->checkUserIntegrity() && $this->checkDoctrineChecksum();
        if(!$integrity && $this->router->getRouteName() != LoginFormAuthenticator::LOGOUT_ROUTE) {

            $token = $this->tokenStorage->getToken();
            if(!$token) return;

            $user = $token->getUser();
            if(!$user) return;

            $notification = new Notification("integrity", [$user]);
            $notification->send("danger");

            $this->tokenStorage->setToken(NULL);

            $response = $this->baseService->redirectToRoute(LoginFormAuthenticator::LOGOUT_ROUTE, [], 302);
            $response->headers->clearCookie('REMEMBERME');
            $event->setResponse($response);

            $event->stopPropagation();
        }
    }

    protected function checkUserIntegrity() {

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

    protected function checkDoctrineChecksum()
    {
        $token = $this->tokenStorage->getToken();
        if(!$token) return true;

        $user = $token->getUser();
        if($user === null) return true;

        $session = $this->requestStack->getSession();
        if (!$session->get("_user_checksum")) return false;

        $md5checksum = md5($this->getDefaultConnectionStr());
        return $md5checksum == $session->get("_user_checksum");
    }
}
