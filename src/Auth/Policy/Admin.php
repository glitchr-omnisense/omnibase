<?php

namespace Base\Auth\Policy;

/**
 * BACKEND (plan "omnibase agnostic", step F5e, slice 1): the back office and the debugging tools are an
 * administrator's. Asked only on their pages (the host says whether the current route is one).
 */
class Admin
{
    public const BACKEND = 'BACKEND';

    public function supports(string $attribute, bool|\Closure $backOffice): bool
    {
        return self::BACKEND === $attribute && ($backOffice instanceof \Closure ? $backOffice() : $backOffice);
    }

    public function decide(mixed $user): bool
    {
        return $user && $user->isGranted('ROLE_ADMIN');
    }
}
