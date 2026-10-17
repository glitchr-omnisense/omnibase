<?php

namespace Base\Bridge\Symfony;

use Base\Contract\Access;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Base\Contract\Access under Symfony: Security's authorization checker (the
 * role hierarchy, the voters - omnibase's on a Thread, a User...) and token
 * storage, the answers of BaseService::isGranted() and of Twig's
 * is_granted() and app.user.
 */
final class SecurityAccess implements Access
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return $this->authorizationChecker->isGranted($attribute, $subject);
    }

    public function getUser(): ?object
    {
        return $this->tokenStorage->getToken()?->getUser();
    }
}
