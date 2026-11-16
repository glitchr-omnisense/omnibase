<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[Embedded]: a property holding an embeddable, stored in the entity's own columns.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['Embedded'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Embedded implements MappingAttribute
{
    public function __construct(
        public readonly string|null $class = null,
        public readonly string|bool|null $columnPrefix = null,
    ) {
    }
}
