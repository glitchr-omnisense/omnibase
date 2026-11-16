<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[SequenceGenerator]: the sequence an identifier is taken from (with #[GeneratedValue(strategy: 'SEQUENCE')]).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['SequenceGenerator'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class SequenceGenerator implements MappingAttribute
{
    public function __construct(
        public readonly string|null $sequenceName = null,
        public readonly int $allocationSize = 1,
        public readonly int $initialValue = 1,
    ) {
    }
}
