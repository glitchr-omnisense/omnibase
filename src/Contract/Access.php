<?php

namespace Base\Contract;

/**
 * What the visitor may do: an attribute (a role, "ROLE_ADMIN", or a
 * permission a voter answers, "edit" on a Thread) granted or not, on a subject
 * or none, and who the visitor is.
 *
 * The Fondation's own contract (plan "omnibase agnostique", C4, D8): an
 * entity's getter asks it instead of the framework's authorization checker.
 * Under Symfony, Base\Bridge\Symfony\SecurityAccess: Security's authorization
 * checker and token storage, the answers of BaseService::isGranted().
 */
interface Access
{
    /** Whether the visitor holds the attribute, on the subject when one is given; false for nobody signed in unless the attribute is granted to anyone. */
    public function isGranted(mixed $attribute, mixed $subject = null): bool;

    /** The visitor signed in (an application's user entity), null for nobody. */
    public function getUser(): ?object;
}
