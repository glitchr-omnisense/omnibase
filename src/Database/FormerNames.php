<?php

namespace Base\Database;

/**
 * The former names of the core's classes that moved to omnibridge/doctrine and
 * omnibridge/symfony (plan "omnibase agnostic", steps F3 and F5b), as data: former =>
 * new, read from src/Resources/former-names.{doctrine,symfony}.php, whose lists
 * src/Resources/former-names.php aliases. For what names a moved class the way it was named before - MetadataSnapshot,
 * which compares the mapping and not where a class lives, and the tests.
 */
final class FormerNames
{
    /** @var array<class-string, class-string>|null */
    private static ?array $moved = null;

    /** @var array<class-string, class-string>|null */
    private static ?array $former = null;

    /** @return array<class-string, class-string> former => new */
    public static function moved(): array
    {
        return self::$moved ??= (require \dirname(__DIR__).'/Resources/former-names.doctrine.php') + (require \dirname(__DIR__).'/Resources/former-names.symfony.php');
    }

    /** The former name of a moved class, null for any other. */
    public static function former(string $class): ?string
    {
        self::$former ??= array_flip(self::moved());

        return self::$former[ltrim($class, '\\')] ?? null;
    }

    /** The former name of a moved class, the class itself for any other. */
    public static function name(string $class): string
    {
        return self::former($class) ?? $class;
    }
}
