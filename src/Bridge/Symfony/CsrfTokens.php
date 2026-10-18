<?php

namespace Base\Bridge\Symfony;

use Base\Contract\Tokens;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The Tokens contract under Symfony (plan "omnibase agnostique", C5): the
 * CSRF token manager's token of an intent - what Twig's `csrf_token()` prints
 * and AbstractController::isCsrfTokenValid() checks.
 */
final class CsrfTokens implements Tokens
{
    public function __construct(private readonly CsrfTokenManagerInterface $manager)
    {
    }

    public function csrf(string $intent): string
    {
        return $this->manager->getToken($intent)->getValue();
    }
}
