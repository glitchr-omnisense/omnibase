<?php

namespace Base\Twig\Extension;

use Base\Service\SecurityPolicy;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes the administrator's account-security policy to every template as
 * `security_policy`.
 *
 * The account settings page receives the same object as a normal render
 * parameter, but the login page and the layout do not have a controller of
 * their own to pass it from - the login form is rendered by the security
 * bundle - and both need to know whether passkeys are offered before they
 * draw a button for them.
 */
class SecurityPolicyTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private SecurityPolicy $securityPolicy)
    {
    }

    public function getGlobals(): array
    {
        return ['security_policy' => $this->securityPolicy];
    }
}
