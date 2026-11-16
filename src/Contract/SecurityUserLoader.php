<?php

namespace Base\Contract;

/*
 * What Base\Repository\UserRepository declares to Doctrine's entity user
 * provider (audit "lesringostars face au master plan", R2): with
 * symfony/doctrine-bridge installed, Base\Bridge\Symfony\SecurityUserLoader -
 * its UserLoaderInterface, a user loaded by its identifier -, under this name;
 * elsewhere (see Base\Contract\Framework) an interface that asks nothing.
 * Apart from Base\Contract\SecurityUserProvider: the two interfaces cannot be
 * one (loadUserByIdentifier() answers ?UserInterface in the loader,
 * UserInterface in the provider), and come from two packages.
 */
if (Framework::declares('Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface')) {
    class_alias(\Base\Bridge\Symfony\SecurityUserLoader::class, SecurityUserLoader::class);
} else {
    /** A users' repository, to a host without Doctrine's entity user provider. */
    interface SecurityUserLoader
    {
    }
}
