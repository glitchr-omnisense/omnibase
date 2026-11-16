<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[ManyToOne]: a to-one association held by this side's column.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['ManyToOne'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ManyToOne implements MappingAttribute
{
    public function __construct(
        public readonly string|null $targetEntity = null,
        public readonly array|null $cascade = null,
        public readonly string $fetch = 'LAZY',
        public readonly string|null $inversedBy = null,
    ) {
    }
}
