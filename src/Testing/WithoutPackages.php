<?php

namespace Base\Testing;

/**
 * Packages made absent from the running process, as in an application that did not install them: the classes of
 * their namespaces no longer load (Composer's autoloader is wrapped, every other class still found by it), so that
 * `class_exists()` answers false and a class standing on them cannot load (omnibase 7.2; omnipack/forge's test
 * helper, promoted). For a test run in its own process: a class of those namespaces already loaded is refused.
 *
 *     #[RunInSeparateProcess]
 *     #[PreserveGlobalState(false)]
 *     public function testTheBundleRunsWithoutDoctrine(): void
 *     {
 *         WithoutPackages::install();                                    // Doctrine's ORM, DBAL and bundle gone
 *         $kernel = new PackageKernel([FaqBundle::class]);               // on omnibridge/eloquent
 *         ...
 *     }
 *
 * DOCTRINE (the default) are the namespaces of doctrine/orm, doctrine/dbal, doctrine/persistence,
 * doctrine/doctrine-bundle, symfony/doctrine-bridge and omnibridge/doctrine - not doctrine/collections
 * (Doctrine\Common\Collections), the standalone library the entities' collections are typed with, nor
 * Doctrine\Common's other parts. Any other namespace can be given (a package's optional dependencies); those of
 * $unchecked may already be loaded (an application's App\, whose aliases the core declares early).
 */
final class WithoutPackages
{
    /** The namespaces of Doctrine's ORM and of its integrations: absent, the persistence is omnibridge/eloquent's. */
    public const DOCTRINE = [
        'Doctrine\\ORM\\',
        'Doctrine\\DBAL\\',
        'Doctrine\\Persistence\\',
        'Doctrine\\Bundle\\DoctrineBundle\\',
        'Doctrine\\Bundle\\FixturesBundle\\',
        'Doctrine\\Bundle\\MigrationsBundle\\',
        'Doctrine\\Migrations\\',
        'Symfony\\Bridge\\Doctrine\\',
        'Omnibridge\\Doctrine\\',
    ];

    /** @var list<string> the namespaces made absent so far */
    private static array $namespaces = [];

    /**
     * Makes the namespaces' classes unloadable from now on, in this process.
     *
     * @param list<string> $namespaces each with its trailing backslash
     * @param list<string> $unchecked  namespaces among them whose classes may be loaded already
     *
     * @throws \LogicException when a class of a checked namespace is loaded already: run the test in its own process
     */
    public static function install(array $namespaces = self::DOCTRINE, array $unchecked = []): void
    {
        $namespaces = array_values(array_diff($namespaces, self::$namespaces));
        if ([] === $namespaces) {
            return;
        }
        foreach (array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits()) as $declared) {
            foreach (array_diff($namespaces, $unchecked) as $namespace) {
                if (str_starts_with($declared, $namespace)) {
                    throw new \LogicException(\sprintf('%s is already loaded: run the test in its own process (#[RunInSeparateProcess]).', $declared));
                }
            }
        }

        $first = [] === self::$namespaces;
        self::$namespaces = array_merge(self::$namespaces, $namespaces);
        if (!$first) {
            return;
        }
        foreach (spl_autoload_functions() as $loader) {
            if (!\is_array($loader) || !$loader[0] instanceof \Composer\Autoload\ClassLoader) {
                continue;
            }
            spl_autoload_unregister($loader);
            spl_autoload_register(static function (string $class) use ($loader): void {
                if (!self::isAbsent($class)) {
                    $loader($class);
                }
            }, true, true);
        }
    }

    /** Whether the class is of a namespace made absent. */
    public static function isAbsent(string $class): bool
    {
        $class = ltrim($class, '\\');
        foreach (self::$namespaces as $namespace) {
            if (str_starts_with($class, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> the namespaces made absent in this process */
    public static function namespaces(): array
    {
        return self::$namespaces;
    }
}
