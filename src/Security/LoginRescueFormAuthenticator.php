<?php

namespace Base\Security;

use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class LoginRescueFormAuthenticator extends LoginFormAuthenticator implements AuthenticationEntryPointInterface
{
    public const LOGIN_ROUTE = 'security_login_rescue';
    public const LOGOUT_ROUTE = 'security_logout';
}
