<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[Id]: the identifier, or a part of it.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['Id'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Id implements MappingAttribute
{
    public function __construct(
        public readonly int $position = 0,
    ) {
    }
}
