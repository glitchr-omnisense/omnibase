<?php

namespace Base\Field;

use Omnibridge\Form\Coverage;

/**
 * What renders each kind on a host: kind => the host's own name for it (a
 * Symfony form type class, a theme's block). The core names no host type: a
 * host registers its map, or the core finds a known adapter's map by its name
 * (ADAPTERS, read only when that class exists).
 *
 * And what each host draws (plan "omnibase agnostic", step F5d): its coverage,
 * every kind declared implemented or unsupported with the reason
 * (omnisense/omnibridge's Form\Coverage), the adapter's COVERAGE constant
 * completed by declare(). faults() names a kind a host leaves undeclared, the
 * shared conformance suite (Base\Field\Conformance) runs every implemented
 * one: a kind added to the core without a host's word is red there, never a
 * plain input in silence.
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
     * MAP (kind => type), optionally ALIASES (type => kind), and COVERAGE
     * (kind => Coverage::IMPLEMENTED or Coverage::unsupported('why')).
     */
    public const ADAPTERS = [
        'symfony' => 'Omnibridge\Symfony\Omnibase\Form\SymfonyKinds',
        'laravel' => 'Omnibridge\Laravel\Form\LaravelKinds',
    ];

    /** @var array<string, array<string, string>> host => kind => type */
    private static array $maps = [];

    /** @var array<string, array<string, string>> host => type => kind */
    private static array $aliases = [];

    /** @var array<string, array<string, string>> host => kind => declaration */
    private static array $coverages = [];

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

    /**
     * Declares what a host draws of some kinds (an application's or a
     * bundle's own, or the host's word in place of its adapter's):
     * kind => Coverage::IMPLEMENTED or Coverage::unsupported('why').
     *
     * @param array<string, string> $coverage
     */
    public static function declare(string $host, array $coverage): void
    {
        self::coverage($host);
        self::$coverages[$host] = $coverage + self::$coverages[$host];
    }

    /**
     * What a host draws: kind => its declaration; [] for a host the core
     * knows no adapter of and nothing declared.
     *
     * @return array<string, string>
     */
    public static function coverage(string $host): array
    {
        if (!isset(self::$coverages[$host])) {
            self::$coverages[$host] = [];
            $adapter = self::ADAPTERS[$host] ?? null;
            if (null !== $adapter && class_exists($adapter) && \defined($adapter.'::COVERAGE')) {
                self::$coverages[$host] = \constant($adapter.'::COVERAGE');
            }
        }

        return self::$coverages[$host];
    }

    /** A kind's declaration on a host; null when it declares none. */
    public static function declaration(string $kind, string $host): ?string
    {
        return self::coverage($host)[$kind] ?? null;
    }

    /**
     * Every kind there is: the core's (Kind::all()) and those an application
     * registered or declared on any host.
     *
     * @return list<string>
     */
    public static function known(): array
    {
        $kinds = Kind::all();
        foreach (array_merge(array_keys(self::$maps), array_keys(self::$coverages)) as $host) {
            foreach (array_keys(self::$maps[$host] ?? []) as $kind) {
                $kinds[] = $kind;
            }
            foreach (array_keys(self::$coverages[$host] ?? []) as $kind) {
                $kinds[] = $kind;
            }
        }

        return array_values(array_unique($kinds));
    }

    /**
     * What is wrong with a host's coverage: a kind left undeclared, a
     * declaration without its reason (Coverage::verify()).
     *
     * @return list<string>
     */
    public static function faults(string $host): array
    {
        return Coverage::verify(self::known(), self::coverage($host));
    }

    /** Forgets what was registered and declared (tests). */
    public static function reset(): void
    {
        self::$maps = [];
        self::$aliases = [];
        self::$coverages = [];
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
