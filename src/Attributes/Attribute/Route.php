<?php

namespace Base\Attributes\Attribute;

use Base\Service\BaseService;
use Symfony\Component\Yaml\Yaml;

/**
 * Attribute class for @Route().
 */

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Route extends \Symfony\Component\Routing\Attribute\Route
{
    /**
     * Path relative to the project directory of the central route path
     * dictionary consulted by $translationKey.
     */
    public const TRANSLATIONS_FILE = "/config/routes/paths.yaml";

    private static ?array $translations = null;

    public function __construct(
        string|array|null $path = null,
        ?string           $name = null,
        array             $requirements = [],
        array             $options = [],
        array             $defaults = [],
        ?string           $host = null,
        ?string           $domain = null,
        ?string           $subdomain = null,
        ?string           $machine = null,
        array|string      $methods = [],
        array|string      $schemes = [],
        ?string           $condition = null,
        ?int              $priority = null,
        ?string           $locale = null,
        ?string           $format = null,
        ?bool             $utf8 = null,
        ?bool             $stateless = null,
        ?string           $env = null,
        ?string           $translationKey = null
    )
    {
        if ($translationKey !== null) {
            $path = self::resolveTranslationKey($translationKey);
        }

        $parsedUrl = parse_url2($host ?? "");
        if (!$parsedUrl) {
            $parsedUrl = [];
        }

        $parsedUrl["domain"] ??= $domain ?? "\{_domain\}";
        $parsedUrl["subdomain"] ??= $subdomain ?? "\{_subdomain\}";
        $parsedUrl["machine"] ??= $machine ?? "\{_machine\}";
        $parsedUrl["port"] ??= $port ?? "\{_port\}";

        $host = compose_url(null, null, null, $parsedUrl["machine"], $parsedUrl["subdomain"], $parsedUrl["domain"], $parsedUrl["port"]);
        parent::__construct($path, $name, $requirements, $options, $defaults, $host, $methods, $schemes, $condition, $priority, $locale, $format, $utf8, $stateless, $env);
    }

    /**
     * Resolves a key from the central route path dictionary into the same
     * per-language path array Symfony's own AttributeClassLoader already
     * accepts natively for $path (one entry per bare-language key) — so the
     * existing per-locale route expansion, matching, and generation are
     * reused entirely unchanged.
     *
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
