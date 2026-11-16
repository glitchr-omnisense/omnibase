<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[GeneratedValue]: how the identifier is generated (AUTO, IDENTITY, SEQUENCE, CUSTOM, NONE).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['GeneratedValue'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class GeneratedValue implements MappingAttribute
{
    public function __construct(
        public readonly string $strategy = 'AUTO',
    ) {
    }
}
