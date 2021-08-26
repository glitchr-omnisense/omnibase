<?php

namespace Base\Service\Traits;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

trait BaseSecurityTrait
{

    public function Logout()
    {
        if (!isset($this->tokenStorage))
            throw new Exception("No token storage found in BaseService. Did you overloaded BaseService::__construct ?");

        $token = $this->getToken();
        if($token)
            return $this->getToken()->setToken(null);
    }

    public function isCsrfTokenValid(string $id, ?string $token): bool
    {
        if (!isset($this->csrfTokenManager))
            throw new Exception("No CSRF token manager found in BaseService. Did you overloaded BaseService::__construct ?");
    
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }
    
    public function getToken()
    {
        if (!isset($this->tokenStorage))
            throw new Exception("No token storage found in BaseService. Did you overloaded BaseService::__construct ?");

        return $this->tokenStorage->getToken();
    }

    public function getUser()
    {
        if (!$token = $this->getToken())
            return null;

        $user = $token->getUser();
        if (!\is_object($user))
            return null;

        if (!$user instanceof UserInterface)
            return null;

        return $user;
    }

    public function isGranted($attribute, $subject = null): bool
    {
        if (!isset($this->authorizationChecker))
            throw new Exception("No authorization checker found in BaseService. Did you overloaded BaseService::__construct ?");

        if ($this->getToken() === null) return false;
        return $this->authorizationChecker->isGranted($attribute, $subject);
    }
}