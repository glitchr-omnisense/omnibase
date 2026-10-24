<?php

namespace Base\Security;

use Base\Entity\User;
use Base\Entity\User\Connection;
use Base\Repository\User\Connections;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The browsers an account signs in from: a userConnection row per browser
 * (its PHPUSERID) and account, with the addresses, agent, hosts, locale and
 * time zones it came with. Framework-free (plan "omniauth", step AU8b): the
 * host says who the browser is ($uniqid: the PHPUSERID its session or its
 * cookie holds, a new one kept in both otherwise) and which host it reached
 * ($host). Base\Security\UserTracker is the Symfony one (its RequestStack and
 * omnibase's router); omnibridge/laravel builds this class on Laravel's
 * session, cookies and request - the same rows, whichever host signed in.
 */
class ConnectionTracker
{
    public const PHPUSERID = "PHPUSERID";

    /**
     * @var Connections
     */
    protected $connectionRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @param (\Closure(): string)|null  $uniqid the browser's PHPUSERID (made and kept by the host when it has none)
     * @param (\Closure(): ?string)|null $host   the host the request reached
     */
    public function __construct(EntityManagerInterface $entityManager, Connections $connectionRepository, private readonly ?\Closure $uniqid = null, private readonly ?\Closure $host = null)
    {
        $this->entityManager = $entityManager;
        $this->connectionRepository = $connectionRepository;
    }

    public function getUniqid(): string
    {
        if (null === $this->uniqid) {
            throw new \LogicException(\sprintf('%s needs the host\'s browser identifier (its $uniqid).', static::class));
        }

        return ($this->uniqid)();
    }

    public function getCurrentConnection(?User $user = NULL, bool $allowNewConnection = true): ?Connection
    {
        // @phpstan-ignore method.notFound (the repository's findOneBy<Fields>() of omnibase's ServiceEntityRepository)
        $connection = $this->connectionRepository->oneByUniqidAndUser($this->getUniqid(), $user);
        if ($connection == NULL && $user != NULL && $allowNewConnection) $connection = $this->createNewConnection($user);

        return $connection;
    }

    public function createNewConnection(User $user): Connection
    {
        $connection = new Connection($this->getUniqid(), $user);
        $this->describe($connection, $user);

        $this->entityManager->persist($connection);
        $this->entityManager->flush();

        return $connection;
    }

    /**
     * On every authenticated request (UserProvider::refreshUser under
     * Symfony, the guard's Authenticated event under Laravel): flushed only
     * when the connection is new or something about it changed - it flushed
     * the whole unit of work on every request, before the controller had
     * even run.
     *
     * @return void
     */
    public function updateConnection(User $user)
    {
        $connection = $this->getCurrentConnection($user, false);
        if (!$connection) {
            $this->getCurrentConnection($user);

            return;
        }

        $before = $this->fingerprint($connection);
        $this->describe($connection, $user);
        if ($this->fingerprint($connection) !== $before) {
            $this->entityManager->flush();
        }
    }

    /** The host the request reached (a connection's hostnames). */
    protected function host(): ?string
    {
        return null !== $this->host ? ($this->host)() : null;
    }

    /**
     * Where from, with what: the request's IP, agent, host, the user's locale
     * and timezone - each only when there is one. A request without a
     * User-Agent (a monitor, curl) or without an address (a worker) got a
     * TypeError, a 500 on every page.
     */
    protected function describe(Connection $connection, User $user): void
    {
        if (is_string($ip = User::getIp()) && '' !== $ip) {
            $connection->addIp($ip);
        }
        if (is_string($agent = User::getAgent()) && '' !== $agent) {
            $connection->setAgent($agent);
        }
        if (is_string($locale = $user->getLocale()) && '' !== $locale) {
            $connection->setLocale($locale);
        }
        $connection->addHostname($this->host());
        $connection->addTimezone($user->getTimezone());
    }

    protected function fingerprint(Connection $connection): string
    {
        return serialize([$connection->getIpList(), $connection->getAgent(), $connection->getLocale(), $connection->getHostnames(), $connection->getTimezones()]);
    }
}
