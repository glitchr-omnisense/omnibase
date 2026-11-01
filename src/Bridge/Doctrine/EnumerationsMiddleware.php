<?php

namespace Base\Bridge\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * omnibase's enumerations registered with DBAL when a connection is made, before any metadata is
 * read through it (plan "omnibase agnostic", step F2a): each an EnumTypeAdapter under its name.
 * DoctrineBundle's doctrine.dbal.types took the enumerations' classes, which were Doctrine types
 * until F2a; Base\DependencyInjection\Compiler\Pass\EnumerationTypesPass moves them from there to
 * this middleware, so that the core's, an application's and a bundle's (omnibase/marketplace
 * prepends its own) are registered as before, at the same moment.
 */
final class EnumerationsMiddleware implements Middleware
{
    /** @param array<string, class-string<\Base\Database\Type\EnumType>> $enumerations by name */
    public function __construct(private readonly array $enumerations = [])
    {
    }

    public function wrap(Driver $driver): Driver
    {
        Types::registerEnumerations($this->enumerations, true);

        return $driver;
    }
}
