<?php

namespace Base\Service\Model;

/**
 * omnibase's class convention, without the bundle: App\Entity\X is
 * Base\Entity\X when the application declares no App\Entity\X of its own - and
 * so for App\Repository, App\Enum, App\Tests, App\Notifier, App\Form. Under
 * Symfony, BaseBundle::warmUp() declares these aliases from its pool and
 * AbstractBaseBundle's last-resort autoloader answers by the same convention
 * (it asks base()); an entity manager built by hand (Base\Bridge\Doctrine\
 * Mapping) registers the convention as an autoloader of its own - an
 * association names its target App\Entity\User\Notification.
 *
 * Composer is asked first: an application's own class, or the stub
 * glitchr/base-plugin wrote for it, is never replaced.
 */
final class ClassAliases
{
    /** The namespaces mapped App\X => Base\X. */
    public const NAMESPACES = ["Entity", "Repository", "Enum", "Tests", "Notifier", "Form"];

    private static bool $registered = false;

    /** The Base class an App class is by convention, null for any other. */
    public static function base(string $class): ?string
    {
        if (!str_starts_with($class, "App\\")) {
            return null;
        }

        $namespace = explode("\\", $class)[1] ?? "";

        return in_array($namespace, self::NAMESPACES, true) ? "Base\\".substr($class, 4) : null;
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
