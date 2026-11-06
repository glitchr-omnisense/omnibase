<?php

namespace Base;

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
 * repositories. docs/20-architecture/services.md.
 */
final class Discovery
{
    public const KINDS = ['autoconfigure', 'collect', 'fallbacks', 'preferred', 'contracts', 'arguments', 'texts', 'storages', 'enumerations', 'repositories'];

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
     * The enumerations of the core and of an application, by their names: the non-abstract classes extending the
     * registry's base class in its folders, each folder in the order the filesystem lists it (as the Symfony bundle
     * has always listed them), an application's over the core's of the same name.
     *
     * @param string|null $packageDir the core's folder (this package's by default)
     *
     * @return array<string, class-string>
     */
    public static function enumerations(string $projectDir, ?string $packageDir = null): array
    {
        $registry = self::registry('enumerations');
        $packageDir ??= \dirname(__DIR__);
        $types = [];
        foreach ($registry['directories'] as $directory) {
            $directory = strtr($directory, ['%package%' => rtrim($packageDir, '/'), '%kernel.project_dir%' => rtrim($projectDir, '/')]);
            foreach (ClassFinder::listed($directory) as $className) {
                if (!is_subclass_of($className, $registry['extends']) || (new \ReflectionClass($className))->isAbstract()) {
                    continue;
                }
                $types[$className::getStaticName()] = $className;
            }
        }

        return $types;
    }
}
