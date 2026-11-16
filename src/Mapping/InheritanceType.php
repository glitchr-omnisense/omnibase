<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[InheritanceType]: how an entity and its subclasses are stored (SINGLE_TABLE, JOINED).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['InheritanceType'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class InheritanceType implements MappingAttribute
{
    public function __construct(
        public readonly string $value,
    ) {
    }
}
