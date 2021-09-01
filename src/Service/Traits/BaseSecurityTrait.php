<?php

namespace Base\Service\Traits;

use Base\Entity\User;
use Base\Entity\User\Notification;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

trait BaseSecurityTrait
{
    public function setUserProperty(string $userProperty)
    {
        User::$property = $userProperty;
        return $this;
    }

    public function Logout()
    {
        if (!isset($this->tokenStorage))
            throw new Exception("No token storage found in BaseService. Did you overloaded BaseService::__construct ?");

        $this->tokenStorage->setToken(null);
        if(array_key_exists("REMEMBERME", $_COOKIE)) 
            setcookie("REMEMBERME", '', time()-1);
    }

    public function isCsrfTokenValid(string $id, $tokenOrForm, ?Request $request = null): bool
    {
        if (!isset($this->csrfTokenManager))
            throw new Exception("No CSRF token manager found in BaseService. Did you overloaded BaseService::__construct ?");

        // Prepare token parameter
       
        $token = null;
        if (!$tokenOrForm instanceof FormInterface) $token = $tokenOrForm;
        else {
            
            $form = $tokenOrForm;
            if($request == null)
                throw new Exception("Request required as FormInterface provided");

            //$form->handleRequest($request); // TBC
            if($request->request->has($form->getName()))
                $token = $request->request->get($form->getName())["_csrf_token"] ?? null;
        }

        // Handling CSRF token exception
        if($token && !is_string($token))
            throw new Exception("Unexpected token value provided: string expected");

        // Checking validity
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