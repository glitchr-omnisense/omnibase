<?php

namespace Base\Bridge\Symfony;

use Base\Contract\Sessions;
use Base\Routing\AdvancedRouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The Sessions contract under Symfony (plan "omnibase agnostique", C4b): what
 * User::logout() did with BaseTrait's token storage and router - the token
 * forgotten when its user is the one signed out, the REMEMBERME cookie cleared
 * on the path and on the site's domain.
 */
final class SecuritySessions implements Sessions
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ?AdvancedRouterInterface $router = null,
    ) {
    }

    public function signOut(object $user): bool
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token || $token->getUser() !== $user) {
            return false;
        }

        $this->tokenStorage->setToken(null);
        setcookie('REMEMBERME', '', time() - 1);
        setcookie('REMEMBERME', '', time() - 1, '/', (string) $this->router?->getDomain());

        return true;
    }
}
