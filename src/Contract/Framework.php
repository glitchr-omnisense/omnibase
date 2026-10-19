<?php

namespace Base\Contract;

/**
 * Whether omnibase's classes declare a framework's interfaces where they would
 * (plan "omnibase agnostique", C7b, C7c): Base\Entity\User implements
 * Security's UserInterface and PasswordAuthenticatedUserInterface and
 * scheb/2fa's models through Base\Contract\SecurityUser and
 * Base\Contract\TwoFactorUser, which are those interfaces when they are
 * installed and empty otherwise.
 *
 * Installed means declared, and nothing else decides: no environment
 * variable, no switch. Whatever loads the user first (a kernel, a unit test,
 * PHP's preloading, a Laravel application reading a vendor that holds
 * Symfony's packages) gets the same class. A host without the framework is a
 * vendor without its packages (`composer install --no-dev` of the core alone,
 * where docker/bin/bare measures the Fondation). Kept as a class, not written
 * out in each contract: the one place of the rule, which a third contract
 * follows the same way.
 */
final class Framework
{
    /**
     * Whether the interfaces named are to be declared: every one of them is
     * installed (autoloaded here).
     *
     * @param class-string ...$interfaces
     */
    public static function declares(string ...$interfaces): bool
    {
        foreach ($interfaces as $interface) {
            if (!interface_exists($interface)) {
                return false;
            }
        }

        return true;
    }
}
