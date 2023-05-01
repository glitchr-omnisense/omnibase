<?php

namespace Base\Annotations\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Annotation class for @Route().
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS", "METHOD"})
 *
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route extends \Symfony\Component\Routing\Annotation\Route
{
    public function __construct(
        string|array    $path = null,
        private ?string $name = null,
        private array   $requirements = [],
        private array   $options = [],
        private array   $defaults = [],
        private ?string $host = null,
        private ?string $domain = null,
        private ?string $subdomain = null,
        private ?string $machine = null,
        array|string    $methods = [],
        array|string    $schemes = [],
        private ?string $condition = null,
        private ?int    $priority = null,
        string          $locale = null,
        string          $format = null,
        bool            $utf8 = null,
        bool            $stateless = null,
        private ?string $env = null
    )
    {
        $parsedUrl = parse_url2($host ?? "");
        if (!$parsedUrl) {
            $parsedUrl = [];
        }

        $parsedUrl["domain"] ??= $domain ?? "\{_domain\}";
        $parsedUrl["subdomain"] ??= $subdomain ?? "\{_subdomain\}";
        $parsedUrl["machine"] ??= $machine ?? "\{_machine\}";
        $parsedUrl["port"] ??= $port ?? "\{_port\}";

        $host = compose_url(null, null, null, $parsedUrl["machine"], $parsedUrl["subdomain"], $parsedUrl["domain"], $parsedUrl["port"]);
        $this->setHost($host);

        parent::__construct($path, $name, $requirements, $options, $defaults, $host, $methods, $schemes, $condition, $priority, $locale, $format, $utf8, $stateless, $env);
    }
}
