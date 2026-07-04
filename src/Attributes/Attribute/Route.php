<?php

namespace Base\Attributes\Attribute;


/**
 * Attribute class for @Route().
 */

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Route extends \Symfony\Component\Routing\Attribute\Route
{
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
        ?string           $env = null
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
        parent::__construct($path, $name, $requirements, $options, $defaults, $host, $methods, $schemes, $condition, $priority, $locale, $format, $utf8, $stateless, $env);
    }
}
