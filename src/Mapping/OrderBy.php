<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[OrderBy]: the order of a to-many association's items.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['OrderBy'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class OrderBy implements MappingAttribute
{
    /**
     * @param array<string, string|\UnitEnum> $value field => ASC|DESC, or an enumeration of the direction (SortDirection)
     */
    public function __construct(
        public readonly array $value,
    ) {
    }
}
