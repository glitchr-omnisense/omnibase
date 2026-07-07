<?php

namespace Tests\Base\Fixtures\Singleton;

use Base\Traits\SingletonTrait;

/**
 * Mirrors the shape that bit AttributeReader in production: a real
 * constructor with required, no-default arguments (there, a handful of
 * injected services). SingletonTrait::getInstance() must never surface the
 * resulting ArgumentCountError to the caller.
 */
class RequiresArgSingleton
{
    use SingletonTrait;

    public function __construct(public readonly string $dependency)
    {
    }
}
