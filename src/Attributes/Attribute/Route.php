<?php

namespace Base\Attributes\Attribute;

use Base\Service\BaseService;
use Composer\InstalledVersions;
use ReflectionProperty;
use Symfony\Component\Yaml\Yaml;

/**
 * Attribute class for @Route().
 */

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Route extends \Symfony\Component\Routing\Attribute\Route
{
    /**
     * Path relative to the project directory of the central route path
     * dictionary consulted when $path is a bare key (see __construct()).
     */
    public const TRANSLATIONS_FILE = "/config/routes_paths.yaml";

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
        ?int              $port = null,
        array|string      $methods = [],
        array|string      $schemes = [],
        ?string           $condition = null,
        ?int              $priority = null,
        ?string           $locale = null,
        ?string           $format = null,
        ?bool             $utf8 = null,
        ?bool             $stateless = null,
        ?string           $env = null
    )
    {
        // A string $path that isn't an actual path (real paths always start
        // with "/") is a key into the central route path dictionary —
        // resolves to the same per-language array Symfony's own
        // AttributeClassLoader already accepts for $path natively.
        if (is_string($path) && $path !== "" && !str_starts_with($path, "/")) {
            $path = self::resolveTranslationKey($path);
        }

        // Host templating is opt-in: a route that asks for none of
        // host/domain/subdomain/machine/port stays host-unconstrained, same
        // as Symfony's own Route attribute. Unconditionally manufacturing a
        // sentinel-templated host for every route — regardless of whether
        // the caller wanted one — silently made every such route
        // unreachable, since nothing ever asks for a Host header that
        // literally matches those tokens.
        if ($host !== null || $domain !== null || $subdomain !== null || $machine !== null || $port !== null) {
            $parsedUrl = parse_url2($host ?? "");
            if (!$parsedUrl) {
                $parsedUrl = [];
            }

            $parsedUrl["domain"] ??= $domain ?? "\{_domain\}";
            $parsedUrl["subdomain"] ??= $subdomain ?? "\{_subdomain\}";
            $parsedUrl["machine"] ??= $machine ?? "\{_machine\}";
            $parsedUrl["port"] ??= $port ?? "\{_port\}";

            $host = self::composeHost($parsedUrl["machine"], $parsedUrl["subdomain"], $parsedUrl["domain"], $parsedUrl["port"]);
        }

        parent::__construct($path, $name, $requirements, $options, $defaults, $host, $methods, $schemes, $condition, $priority, $locale, $format, $utf8, $stateless, $env);
    }

    /**
     * A bare host (no scheme, no path) — compose_url() is a full-URL
     * composer and always prepends a scheme once a domain is set, which
     * silently baked "https://"/"http://" into every route's Host
     * attribute. A real Host request header never carries a scheme, so
     * that compiled host requirement could never match a real request —
     * every route relying on this templating always fell through to
     * whatever route came next in the collection.
     */
    private static function composeHost(?string $machine, ?string $subdomain, string $domain, string|int|null $port): string
    {
        $machinePart = $machine ? $machine . "." : "";
        $subdomainPart = $subdomain ? $subdomain . "." : "";
        $portPart = ($port && !in_array($port, [80, 443], false)) ? ":" . $port : "";

        return $machinePart . $subdomainPart . $domain . $portPart;
    }

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
     * #[Route] attributes are reflected and instantiated during Symfony's
     * own container compilation (AttributeAutoconfigurationPass scans every
     * attribute on every class, controllers included) — well before
     * BaseBundle::boot() has run, so BaseService::getProjectDir() (which
     * throws when unset) is not reliably available yet. Peek at its backing
     * property directly (no throw), falling back to Composer's
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
