<?php

namespace Base\Security;

use App\Entity\User;
use Base\Repository\User\Connections;
use Base\Routing\AdvancedRouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base\Security\ConnectionTracker under Symfony: the browser's PHPUSERID
 * from the request's session or cookie (a new one kept in both), the host
 * from omnibase's router.
 */
class UserTracker extends ConnectionTracker
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var AdvancedRouterInterface
     */
    protected $router;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack, AdvancedRouterInterface $router, Connections $connectionRepository)
    {
        parent::__construct($entityManager, $connectionRepository);

        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    public function getUniqid(): string
    {
        $session = $this->requestStack->getSession();
        $cookies = $this->requestStack->getMainRequest()->cookies;

        $uniqid = $session?->get(self::PHPUSERID) ?? $cookies?->get(self::PHPUSERID) ?? null;
        if(!$uniqid) {

            $uniqid = uniqid("", true);
            $session->set(self::PHPUSERID, $uniqid);
            // Once output has started (a command, a test run) the cookie cannot be
            // sent and setcookie() only warns - a warning PHPUnit turns into the
            // failure of the sign-in under test. The session keeps the identifier.
            if (!headers_sent()) {
                setcookie(self::PHPUSERID, $uniqid, 0, "/", $this->router->getDomain());
            }
        }

        return $uniqid;
    }

    protected function host(): ?string
    {
        return $this->router->getHost();
    }
}
