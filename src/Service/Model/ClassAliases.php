<?php

namespace Base\Service\Model;

/**
 * omnibase's class convention, without the bundle: App\Entity\X is
 * Base\Entity\X when the application declares no App\Entity\X of its own - and
 * so for App\Repository, App\Enum, App\Tests, App\Notifier, App\Form (the core's
 * repositories and form types under their bridges' names, MOVED). Under
 * Symfony, BaseBundle::warmUp() declares these aliases from its pool and
 * AbstractBaseBundle's last-resort autoloader answers by the same convention
 * (it asks base()); an entity manager built by hand (Base\Bridge\Doctrine\
 * Mapping) registers the convention as an autoloader of its own - an
 * association names its target App\Entity\User\Notification.
 *
 * Composer is asked first: an application's own class is never replaced
 * (the stubs omnibridge/composer writes for the others, from the core's
 * Base\Bridge\Composer\Stub providers, are read by no autoloader).
 */
final class ClassAliases
{
    /** The namespaces mapped App\X => Base\X. */
    public const NAMESPACES = ["Entity", "Repository", "Enum", "Tests", "Notifier", "Form"];

    private static bool $registered = false;

    /**
     * Where the classes of App\Repository and App\Form live besides Base\: the core's repositories in
     * omnibridge/doctrine (plan "omnibase agnostic", F3), its form types and extensions in omnibridge/symfony
     * (F5b). 5.x reached them through their former names (Base\Repository\X, Base\Form\Type\X), aliased
     * by the core; since 6.0 they are found under their own.
     */
    public const MOVED = [
        "Repository" => "Omnibridge\\Doctrine\\Omnibase\\Repository\\",
        "Form" => "Omnibridge\\Symfony\\Omnibase\\Form\\",
    ];

    /**
     * The class an App class is by convention, null for any other: Base\X, or - for App\Repository\X
     * and App\Form\X that the core does not hold itself - the bridge's class of that path when it exists.
     */
    public static function base(string $class): ?string
    {
        if (!str_starts_with($class, "App\\")) {
            return null;
        }

        $namespace = explode("\\", $class)[1] ?? "";
        if (!in_array($namespace, self::NAMESPACES, true)) {
            return null;
        }

        $base = "Base\\".substr($class, 4);
        if (isset(self::MOVED[$namespace]) && !self::exists($base)) {
            $moved = self::MOVED[$namespace].substr($class, 5 + strlen($namespace));
            if (self::exists($moved)) {
                return $moved;
            }
        }

        return $base;
    }

    private static function exists(string $class): bool
    {
        return class_exists($class) || interface_exists($class) || trait_exists($class);
    }

    /** The convention as an autoloader, appended after Composer's: once a process. */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        spl_autoload_register(static function (string $class): void {
            $input = self::base($class);
            if ($input === null) {
                return;
            }

            if ((class_exists($input) || interface_exists($input) || trait_exists($input))
                && !class_exists($class, false) && !interface_exists($class, false) && !trait_exists($class, false)) {
                class_alias($input, $class);
            }
        });
    }
}
