<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[OneToOne]: a to-one association, one row each side.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['OneToOne'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class OneToOne implements MappingAttribute
{
    /**
     * @param array<string>|null $cascade
     */
    public function __construct(
        public readonly string|null $targetEntity = null,
        public readonly string|null $mappedBy = null,
        public readonly string|null $inversedBy = null,
        public readonly array|null $cascade = null,
        public readonly string $fetch = 'LAZY',
        public readonly bool $orphanRemoval = false,
    ) {
    }
}
