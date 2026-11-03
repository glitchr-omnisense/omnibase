<?php

namespace Base\Database\Backend;

/**
 * What a persistence knows of its column types that the core's code asks
 * (plan "omnibase agnostic", step F5c, item 3): whether a field of a type
 * holds a PHP array, which EntityHydrator gives an empty one by default.
 * Doctrine's answers by its DBAL types (a JsonType, a set enumeration);
 * registered per unit of work in Base\Database\Attribute\Backend\Backends.
 * A persistence registering none is read by the neutral rule: `json`, or an
 * enumeration that is a SetType (EntityHydrator::holdsArray()).
 */
interface ColumnTypes
{
    /** Whether a field of that type (its name, as the shape gives it) holds a PHP array. */
    public function isArray(string $type): bool;
}
