<?php

namespace Base\Contract;

/*
 * What Base\Entity\User declares to a framework's security (plan "omnibase
 * agnostique", C7b): with symfony/security-core installed,
 * Base\Bridge\Symfony\SecurityUser - Security's UserInterface and
 * PasswordAuthenticatedUserInterface -, under this name; elsewhere (no
 * security-core installed, see Base\Contract\Framework) an interface that
 * asks nothing. The user's methods (getUserIdentifier(), getRoles(),
 * getPassword(), eraseCredentials()) are the same either way: only what PHP
 * checks them against changes.
 *
 * Declared at the file's level, not as a class of its own: the autoloader
 * loads this file once, when the user is loaded, and the choice is made then.
 */
if (Framework::declares(
    'Symfony\Component\Security\Core\User\UserInterface',
    'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface',
)) {
    class_alias(\Base\Bridge\Symfony\SecurityUser::class, SecurityUser::class);
} else {
    /** A user, to a host that has no security of its own to declare it to. */
    interface SecurityUser
    {
    }
}
