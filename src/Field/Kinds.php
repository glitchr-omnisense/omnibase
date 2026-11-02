<?php

namespace Base\Field;

/**
 * What renders each kind on a host: kind => the host's own name for it (a
 * Symfony form type class, a theme's block). The core names no host type: a
 * host registers its map, or the core finds a known adapter's map by its name
 * (ADAPTERS, read only when that class exists).
 *
 * Several kinds may share one host type (id and discriminator are Symfony's
 * HiddenType); type() answers a kind, kind() the first kind of a type, then
 * the map's ALIASES (Symfony types no descriptor names: ChoiceType is a
 * choice, CheckboxType a checkbox).
 */
final class Kinds
{
    /**
     * The adapters' maps the core knows by name: a class with the constants
     * MAP (kind => type) and, optionally, ALIASES (type => kind).
     */
    public const ADAPTERS = [
        'symfony' => 'Base\Bridge\Symfony\Form\SymfonyKinds',
    ];

    /** @var array<string, array<string, string>> host => kind => type */
    private static array $maps = [];

    /** @var array<string, array<string, string>> host => type => kind */
    private static array $aliases = [];

    private function __construct()
    {
    }

    /**
     * Registers (or completes) a host's map: an application's or a bundle's
     * own kinds, or a whole host's map in place of the known one.
     *
     * @param array<string, string> $map     kind => type
     * @param array<string, string> $aliases type => kind
     */
    public static function register(string $host, array $map, array $aliases = []): void
    {
        self::load($host);
        self::$maps[$host] = $map + (self::$maps[$host] ?? []);
        self::$aliases[$host] = $aliases + (self::$aliases[$host] ?? []);
    }

    /** The host's type of a kind; null for an unknown kind, or with no map for that host. */
    public static function type(?string $kind, string $host = 'symfony'): ?string
    {
        if (null === $kind) {
            return null;
        }

        return self::load($host)[$kind] ?? null;
    }

    /** The kind a host's type renders; null when the map does not know that type. */
    public static function kind(?string $type, string $host = 'symfony'): ?string
    {
        if (null === $type) {
            return null;
        }
        $type = ltrim($type, '\\');
        $kind = array_search($type, self::load($host), true);
        if (false !== $kind) {
            return $kind;
        }

        return self::$aliases[$host][$type] ?? null;
    }

    /** @return array<string, string> kind => type */
    public static function map(string $host = 'symfony'): array
    {
        return self::load($host);
    }

    /** Forgets what was registered (tests). */
    public static function reset(): void
    {
        self::$maps = [];
        self::$aliases = [];
    }

    /** @return array<string, string> */
    private static function load(string $host): array
    {
        if (!isset(self::$maps[$host])) {
            self::$maps[$host] = [];
            self::$aliases[$host] = [];
            $adapter = self::ADAPTERS[$host] ?? null;
            if (null !== $adapter && class_exists($adapter)) {
                self::$maps[$host] = \constant($adapter.'::MAP');
                self::$aliases[$host] = \defined($adapter.'::ALIASES') ? \constant($adapter.'::ALIASES') : [];
            }
        }

        return self::$maps[$host];
    }
}
