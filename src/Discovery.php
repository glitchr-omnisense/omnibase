<?php

namespace Base;

use Base\Service\Model\ClassAliases;
use Base\Service\Model\ClassFinder;

/**
 * What a host discovers among the core's services, as data (plan "omnibase agnostic", step F5e slice 4c; §2.5: the
 * declaration is the core's, a bridge compiles it): the registries of src/Resources/discovery.php - what the 21 compiler
 * passes of omnibridge/symfony looked for until then, each in its own class. omnibridge/symfony compiles them in one
 * generic pass, omnibridge/laravel applies those it needs to what it binds (the collections, the fallbacks, the entity
 * listeners' events, the repositories), and Omnibridge\Container builds with the generic kinds (`autoconfigure`,
 * `collect`, `fallbacks`: omnisense/omnibridge's Container\Discovery format).
 *
 * Kinds (KINDS): autoconfigure, collect, fallbacks, preferred, contracts, arguments, texts, storages, enumerations,
 * repositories, entities. docs/20-architecture/services.md.
 *
 * The packages (omnibase 7.2): a package is what a host installs beside the core and whose src/ holds its classes
 * under one namespace - an omnipack -, given to entities() and enumerations() as its folder => its src/'s namespace.
 * Symfony gives its registered bundles (omnibridge/symfony), any other host the Composer packages that require the
 * core (packages()). Each package's src/Entity is then described with the core's and the application's, by every
 * persistence (the `entities` kind: what DoctrineBundle's auto_mapping did for a bundle, now without Doctrine too),
 * its src/Enum's enumerations are column types, and the interfaces of its src/ that carry #[ResolveTargetEntity]
 * resolve their associations (targets()). docs/20-architecture/entities-and-repositories.md.
 */
final class Discovery
{
    public const KINDS = ['autoconfigure', 'collect', 'fallbacks', 'preferred', 'contracts', 'arguments', 'texts', 'storages', 'enumerations', 'repositories', 'entities'];

    /** @var array<string, mixed>|null */
    private static ?array $registries = null;

    /**
     * Every registry, by kind.
     *
     * @return array<string, mixed>
     */
    public static function registries(): array
    {
        return self::$registries ??= require __DIR__.'/Resources/discovery.php';
    }

    /**
     * One registry.
     *
     * @return array<mixed>
     */
    public static function registry(string $kind): array
    {
        if (!\in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException(sprintf('No registry "%s" in omnibase\'s discovery: %s.', $kind, implode(', ', self::KINDS)));
        }

        return self::registries()[$kind];
    }

    /**
     * The enumerations of the core, of the packages and of an application, by their names: the non-abstract classes
     * extending the registry's base class in its folders, each folder in the order the filesystem lists it (as the
     * Symfony bundle has always listed them), a package's over the core's and an application's over both of the same
     * name.
     *
     * @param string|null           $packageDir the core's folder (this package's by default)
     * @param array<string, string> $packages   the packages (packages()): folder => its src/'s namespace
     *
     * @return array<string, class-string>
     */
    public static function enumerations(string $projectDir, ?string $packageDir = null, array $packages = []): array
    {
        $registry = self::registry('enumerations');
        $packageDir ??= \dirname(__DIR__);
        $types = [];
        foreach (self::expand($registry['directories'], $projectDir, [rtrim($packageDir, '/') => 'Base\\', ...$packages]) as $directory => $_) {
            foreach (ClassFinder::listed($directory) as $className) {
                // App\Enum\X is the application's own where it declares one, the core's otherwise.
                if (str_starts_with($className, 'Base\\Enum\\')) {
                    ClassAliases::register();
                    $className = (new \ReflectionClass('App\\'.substr($className, \strlen('Base\\'))))->getName();
                }
                if (!is_subclass_of($className, $registry['extends']) || (new \ReflectionClass($className))->isAbstract()) {
                    continue;
                }
                $types[$className::getStaticName()] = $className;
            }
        }

        return $types;
    }

    /**
     * The folders of mapped classes the persistences describe, each with its classes' namespace (what Doctrine's
     * attribute mapping needs as its prefix): the registry's `directories` for the core, for each package given and for
     * the application, then the folders the application or a package adds (`include`, the entity_folders option:
     * folder => namespace, null when the folder's own files say it). A folder that does not exist is left out. Folders
     * to leave out inside them are the hosts' to pass on (EntitySchema's `exclude` option; Doctrine's excluded paths).
     *
     * @param array<string, string>      $packages the packages (packages(), a host's bundles): folder => its src/'s namespace
     * @param array<string, string|null> $include  more folders: folder => namespace
     *
     * @return array<string, string> folder => namespace, the core's first
     */
    public static function entities(string $projectDir, array $packages = [], array $include = []): array
    {
        $registry = self::registry('entities');
        $folders = self::expand($registry['directories'], $projectDir, [\dirname(__DIR__) => 'Base\\', ...$packages]);
        foreach ($include as $directory => $namespace) {
            $directory = rtrim((string) $directory, '/');
            if (!is_dir($directory) || isset($folders[$directory])) {
                continue;
            }
            $folders[$directory] = rtrim($namespace ?? (ClassFinder::namespaces($directory)[0] ?? ''), '\\');
        }

        return $folders;
    }

    /**
     * The packages Composer installed that require the core (an omnipack, a bundle of the family), each by its folder
     * with the namespace of its src/ (its PSR-4 autoload): what a host that registers no bundle (Laravel, a plain PHP
     * script) gives entities() and enumerations(). Read from Composer's vendor/composer/installed.json, the packages in
     * the order Composer lists them.
     *
     * @param string|null $vendorDir Composer's vendor folder (the running autoloader's by default)
     *
     * @return array<string, string> folder => namespace
     */
    public static function packages(?string $vendorDir = null): array
    {
        $registry = self::registry('entities');
        $vendorDir ??= class_exists(\Composer\Autoload\ClassLoader::class) ? \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2) : null;
        $installed = null === $vendorDir ? null : rtrim($vendorDir, '/').'/composer/installed.json';
        if (null === $installed || !is_file($installed)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($installed), true);
        $packages = [];
        foreach ((\is_array($data) ? ($data['packages'] ?? $data) : []) as $package) {
            if (!\is_array($package) || !isset($package['require'][$registry['requires']], $package['install-path'])) {
                continue;
            }
            $folder = realpath(\dirname($installed).'/'.$package['install-path']);
            if (false === $folder) {
                continue;
            }
            foreach ((array) ($package['autoload']['psr-4'] ?? []) as $namespace => $paths) {
                foreach ((array) $paths as $path) {
                    if ('src' === trim((string) $path, './')) {
                        $packages[$folder] = rtrim((string) $namespace, '\\');
                        continue 3;
                    }
                }
            }
        }

        return $packages;
    }

    /**
     * The interfaces of the packages that declare the entity their associations target (#[ResolveTargetEntity]),
     * interface => class: what Doctrine is told (its resolve_target_entities) where the package is installed. Read
     * from the files of each package's src/ named *Interface.php that name the attribute; only those are loaded.
     *
     * @param array<string, string> $packages the packages: folder => its src/'s namespace
     *
     * @return array<string, string>
     */
    public static function targets(array $packages): array
    {
        $targets = [];
        foreach ($packages as $folder => $namespace) {
            $src = rtrim((string) $folder, '/').'/src';
            if (!is_dir($src)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Interface.php') || !str_contains((string) file_get_contents($file->getPathname()), 'ResolveTargetEntity')) {
                    continue;
                }
                $relative = substr($file->getPathname(), \strlen($src) + 1, -\strlen('.php'));
                $interface = rtrim($namespace, '\\').'\\'.str_replace('/', '\\', $relative);
                if (null !== $class = \Base\Database\Attribute\ResolveTargetEntity::of($interface)) {
                    $targets[$interface] = $class;
                }
            }
        }
        ksort($targets);

        return $targets;
    }

    /**
     * A registry's folders made real: %package% for each package (the core first), %kernel.project_dir% for the
     * application, each folder that exists with the namespace its package's src/ gives it.
     *
     * @param list<string>          $directories the registry's
     * @param array<string, string> $packages    folder => its src/'s namespace
     *
     * @return array<string, string> folder => namespace
     */
    private static function expand(array $directories, string $projectDir, array $packages): array
    {
        $folders = [];
        foreach ($directories as $directory) {
            $roots = str_contains($directory, '%package%') ? $packages : [rtrim($projectDir, '/') => 'App'];
            foreach ($roots as $root => $namespace) {
                $folder = strtr($directory, ['%package%' => rtrim((string) $root, '/'), '%kernel.project_dir%' => rtrim($projectDir, '/')]);
                if (isset($folders[$folder]) || !is_dir($folder)) {
                    continue;
                }
                // src/Entity of a package whose src/ is Base\Faq: Base\Faq\Entity.
                $folders[$folder] = rtrim($namespace, '\\').'\\'.str_replace('/', '\\', substr($directory, strrpos($directory, '/src/') + \strlen('/src/')));
            }
        }

        return $folders;
    }
}
