<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[CustomIdGenerator]: the class that generates the identifier (with #[GeneratedValue(strategy: 'CUSTOM')]).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['CustomIdGenerator'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class CustomIdGenerator implements MappingAttribute
{
    public function __construct(
        public readonly string|null $class = null,
    ) {
    }
}
