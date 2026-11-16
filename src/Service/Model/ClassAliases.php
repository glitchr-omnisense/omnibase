<?php

namespace Base\Service\Model;

/**
 * omnibase's class convention: App\Entity\X is Base\Entity\X when the
 * application declares no App\Entity\X of its own - and so for App\Repository,
 * App\Enum, App\Tests, App\Notifier, App\Form, App\EntitySubscriber,
 * App\EntityDispatcher and App\Console: the application's tree mirrors the
 * provided one, whoever provides the Base\ class (the core, a bundle, a host's
 * package). The host registers the convention as an autoloader (register()).
 *
 * Composer is asked first: an application's own class is never replaced
 * (the stubs omnibridge/composer writes for the others, from the core's
 * Base\Tooling\Composer\Stub providers, are read by no autoloader).
 */
final class ClassAliases
{
    /** The namespaces mapped App\X => Base\X. */
    public const NAMESPACES = ["Entity", "Repository", "Enum", "Tests", "Notifier", "Form", "EntitySubscriber", "EntityDispatcher", "Console"];

    private static bool $registered = false;

    /** The class an App class is by convention (Base\X), null for any other. */
    public static function base(string $class): ?string
    {
        if (!str_starts_with($class, "App\\")) {
            return null;
        }

        $namespace = explode("\\", $class)[1] ?? "";
        if (!in_array($namespace, self::NAMESPACES, true)) {
            return null;
        }

        return "Base\\".substr($class, 4);
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
