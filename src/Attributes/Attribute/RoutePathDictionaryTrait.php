<?php

namespace Base\Attributes\Attribute;

use Base\Service\BaseService;
use Composer\InstalledVersions;
use ReflectionProperty;
use Symfony\Component\Yaml\Yaml;

/**
 * Resolves a $translationKey against the central route path dictionary
 * (config/routes_paths.yaml, relative to the project dir) into the same
 * per-language path array Symfony's own AttributeClassLoader already
 * accepts natively for a Route's $path (one entry per bare-language key) —
 * so per-locale route expansion, matching, and generation are all reused
 * entirely unchanged; this is purely a constructor-time lookup.
 *
 * Deliberately NOT under config/routes/: some apps' Kernel::configureRoutes()
 * auto-imports every *.yaml file directly in that directory as a routing
 * config file in its own right, which would double-load this file and try
 * to parse per-language keys ("en", "fr", ...) as route definitions.
 *
 * Shared by both Route (host/domain/subdomain-templated) and LocalizedRoute
 * (a plain Symfony Route subclass, no host templating) — each using class
 * gets its own memoized $translations cache, since trait static properties
 * are per-class.
 */
trait RoutePathDictionaryTrait
{
    public const TRANSLATIONS_FILE = "/config/routes_paths.yaml";

    private static ?array $translations = null;

    /**
     * @return array<string, string>
     */
    private static function resolveTranslationKey(string $key): array
    {
        if (self::$translations === null) {
            $file = self::projectDir() . self::TRANSLATIONS_FILE;
            self::$translations = is_file($file) ? (Yaml::parseFile($file) ?? []) : [];
        }

        if (!isset(self::$translations[$key])) {
            throw new \LogicException(sprintf(
                'No route path translations found for key "%s" in "%s".',
                $key,
                self::TRANSLATIONS_FILE
            ));
        }

        return self::$translations[$key];
    }

    /**
     * #[Route]/#[LocalizedRoute] attributes are reflected and instantiated
     * during Symfony's own container compilation (AttributeAutoconfigurationPass
     * scans every attribute on every class, controllers included) — well
     * before BaseBundle::boot() has run, so BaseService::getProjectDir()
     * (which throws when unset) is not reliably available yet. Peek at its
     * backing property directly (no throw), falling back to Composer's
     * InstalledVersions, which needs no kernel/container at all.
     */
    private static function projectDir(): string
    {
        $projectDir = (new ReflectionProperty(BaseService::class, 'projectDir'))->getValue();
        if ($projectDir !== null) {
            return $projectDir;
        }

        $installPath = InstalledVersions::getRootPackage()['install_path'] ?? getcwd();

        return realpath($installPath) ?: $installPath;
    }
}
