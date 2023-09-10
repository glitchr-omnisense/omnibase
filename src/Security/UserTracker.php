<?php

namespace Base\Security;

use App\Entity\User;
use Base\Repository\User\ConnectionRepository;
use Base\Entity\User\Connection;
use Base\Enum\ConnectionState;
use Base\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 *
 */
class UserTracker
{
    public const PHPUSERID = "PHPUSERID";

    /**
     * @var ConnectionRepository
     */
    protected $connectionRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var RouterInterface
     */
    protected $router;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack, RouterInterface $router, ConnectionRepository $connectionRepository)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->router = $router;

        $this->connectionRepository = $connectionRepository;
    }

    public function getUniqid(): string
    {
        $session = $this->requestStack->getSession();
        $cookies = $this->requestStack->getMainRequest()->cookies;

        $uniqid = $session?->get(self::PHPUSERID) ?? $cookies?->get(self::PHPUSERID) ?? null;
        if(!$uniqid) {

            $uniqid = uniqid("", true);
            $session->set(self::PHPUSERID, $uniqid);
            setcookie(self::PHPUSERID, $uniqid, 0, "/", $this->router->getDomain());
        }

        return $uniqid;
    }

    public function getCurrentConnection(?User $user = NULL, bool $allowNewConnection = true): ?Connection
    {
        $connection = $this->connectionRepository->findOneByUniqidAndUser($this->getUniqid(), ["user" => $user]);
        if ($connection == NULL && $user != NULL && $allowNewConnection) $connection = $this->createNewConnection($user);

        return $connection;
    }

    public function createNewConnection(User $user): Connection
    {
        $connection = new Connection($this->getUniqid(), $user);
        $connection->addIp(User::getIp());
        $connection->setAgent(User::getAgent());
        $connection->setLocale($user->getLocale());
        $connection->addHostname($this->router->getHost());
        $connection->addTimezone($user->getTimezone());

        $this->entityManager->persist($connection);
        $this->entityManager->flush();

        return $connection;
    }

    public function updateConnection(User $user)
    {
        $connection = $this->getCurrentConnection($user);
        
        $connection->addIp(User::getIp());
        $connection->setAgent(User::getAgent());
        $connection->setLocale($user->getLocale());
        $connection->addHostname($this->router->getHost());
        $connection->addTimezone($user->getTimezone());

        $this->entityManager->flush();
    }
}