<?php

namespace Base\Contract;

/**
 * Whether omnibase's classes declare a framework's interfaces where they would
 * (plan "omnibase agnostique", C7b): Base\Entity\User implements Security's
 * UserInterface and PasswordAuthenticatedUserInterface and scheb/2fa's
 * models through Base\Contract\SecurityUser and Base\Contract\TwoFactorUser,
 * which are those interfaces when they are installed and empty otherwise.
 *
 * Installed is not always running: a Laravel application reading a vendor
 * that holds Symfony's packages (lesringostars-laravel reads lesringostars'),
 * or docker/bin/bare, which measures the core as a host without the framework
 * loads it, say they run without it, and the user then declares none of the
 * framework's interfaces. Either by the environment, OMNIBASE_FRAMEWORK=none
 * (set before PHP starts: no order to keep), or by Framework::without() before
 * Base\Entity\User is loaded. Under Symfony nothing is said: the interfaces
 * are declared whatever loads the user first (a kernel, a unit test, PHP's
 * preloading), as before.
 */
final class Framework
{
    public const ENV = 'OMNIBASE_FRAMEWORK';

    private static ?bool $without = null;

    /** The host runs without the framework (true), or says nothing more (null: the environment decides). */
    public static function without(?bool $without = true): void
    {
        self::$without = $without;
    }

    public static function isWithout(): bool
    {
        if (null !== self::$without) {
            return self::$without;
        }

        $env = getenv(self::ENV);
        if (false === $env) {
            $env = $_SERVER[self::ENV] ?? $_ENV[self::ENV] ?? '';
        }

        return 'none' === strtolower(trim((string) $env));
    }

    /**
     * Whether the interfaces named are to be declared: every one of them is
     * installed (autoloaded here) and the host has not said it runs without
     * the framework.
     *
     * @param class-string ...$interfaces
     */
    public static function declares(string ...$interfaces): bool
    {
        if (self::isWithout()) {
            return false;
        }
        foreach ($interfaces as $interface) {
            if (!interface_exists($interface)) {
                return false;
            }
        }

        return true;
    }
}
