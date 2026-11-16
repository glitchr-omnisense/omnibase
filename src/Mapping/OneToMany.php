<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[OneToMany]: the to-many side of a many-to-one association (mappedBy).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['OneToMany'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class OneToMany implements MappingAttribute
{
    /**
     * @param array<string>|null $cascade
     */
    public function __construct(
        public readonly string|null $targetEntity = null,
        public readonly string|null $mappedBy = null,
        public readonly array|null $cascade = null,
        public readonly string $fetch = 'LAZY',
        public readonly bool $orphanRemoval = false,
        public readonly string|null $indexBy = null,
    ) {
    }
}
