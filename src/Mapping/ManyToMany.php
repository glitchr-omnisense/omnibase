<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[ManyToMany]: a to-many association through a join table.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['ManyToMany'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ManyToMany implements MappingAttribute
{
    /**
     * @param array<string>|null $cascade
     */
    public function __construct(
        public readonly string $targetEntity,
        public readonly string|null $mappedBy = null,
        public readonly string|null $inversedBy = null,
        public readonly array|null $cascade = null,
        public readonly string $fetch = 'LAZY',
        public readonly bool $orphanRemoval = false,
        public readonly string|null $indexBy = null,
    ) {
    }
}
