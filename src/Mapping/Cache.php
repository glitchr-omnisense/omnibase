<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[Cache]: the second-level cache of an entity or an association.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['Cache'].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
final class Cache implements MappingAttribute
{
    public function __construct(
        public readonly string $usage = 'READ_ONLY',
        public readonly string|null $region = null,
    ) {
    }
}
