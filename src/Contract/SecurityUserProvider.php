<?php

namespace Base\Contract;

/*
 * What Base\Repository\UserRepository declares to a framework's security
 * (audit "lesringostars face au master plan", R2; the pattern of
 * Base\Contract\SecurityUser, C7b): with symfony/security-core installed,
 * Base\Bridge\Symfony\SecurityUserProvider - Security's user provider and its
 * password upgrader -, under this name; elsewhere (see Base\Contract\Framework)
 * an interface that asks nothing. The repository's methods (refreshUser(),
 * supportsClass(), loadUserByIdentifier(), upgradePassword()) are the same
 * either way: only what PHP checks them against changes. Their signatures name
 * Security's types, which PHP does not load to declare them; they are
 * Security's to call. Its loader is Base\Contract\SecurityUserLoader.
 *
 * Declared at the file's level, not as a class of its own: the autoloader
 * loads this file once, when the repository is loaded, and the choice is made
 * then.
 */
if (Framework::declares(
    'Symfony\Component\Security\Core\User\UserProviderInterface',
    'Symfony\Component\Security\Core\User\PasswordUpgraderInterface',
)) {
    class_alias(\Base\Bridge\Symfony\SecurityUserProvider::class, SecurityUserProvider::class);
} else {
    /** A users' repository, to a host that has no security of its own to declare it to. */
    interface SecurityUserProvider
    {
    }
}
