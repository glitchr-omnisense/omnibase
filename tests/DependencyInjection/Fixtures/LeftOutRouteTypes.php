<?php

namespace Tests\Base\DependencyInjection\Fixtures;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * WithoutSuggestedBundlesKernel's routing loader for the types of the bundles it
 * leaves out ("api_platform", "webauthn"): no route, as an application without
 * those bundles has none of theirs.
 */
final class LeftOutRouteTypes extends Loader
{
    /** @param list<string> $types */
    public function __construct(private readonly array $types, ?string $env = null)
    {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return new RouteCollection();
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return \in_array($type, $this->types, true);
    }
}
