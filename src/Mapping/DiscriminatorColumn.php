<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[DiscriminatorColumn]: the column that tells the class of a row in an inheritance.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['DiscriminatorColumn'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class DiscriminatorColumn implements MappingAttribute
{
    public function __construct(
        public readonly string|null $name = null,
        public readonly string|null $type = null,
        public readonly int|null $length = null,
        public readonly string|null $columnDefinition = null,
        public readonly string|null $enumType = null,
        public readonly array $options = [],
    ) {
    }
}
