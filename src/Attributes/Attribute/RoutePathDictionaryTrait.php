<?php

namespace Base\Attributes\Attribute;

use Base\Service\BaseService;
use Symfony\Component\Yaml\Yaml;

/**
 * Resolves a $translationKey against the central route path dictionary
 * (config/routes/paths.yaml, relative to the project dir) into the same
 * per-language path array Symfony's own AttributeClassLoader already
 * accepts natively for a Route's $path (one entry per bare-language key) —
 * so per-locale route expansion, matching, and generation are all reused
 * entirely unchanged; this is purely a constructor-time lookup.
 *
 * Shared by both Route (host/domain/subdomain-templated) and LocalizedRoute
 * (a plain Symfony Route subclass, no host templating) — each using class
 * gets its own memoized $translations cache, since trait static properties
 * are per-class.
 */
trait RoutePathDictionaryTrait
{
    public const TRANSLATIONS_FILE = "/config/routes/paths.yaml";

    private static ?array $translations = null;

    /**
     * @return array<string, string>
     */
    private static function resolveTranslationKey(string $key): array
    {
        if (self::$translations === null) {
            $file = BaseService::getProjectDir() . self::TRANSLATIONS_FILE;
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
}
