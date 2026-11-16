<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[JoinTable]: the table of a many-to-many association.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['JoinTable'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class JoinTable implements MappingAttribute
{
    public readonly array $joinColumns;
    public readonly array $inverseJoinColumns;

    public function __construct(
        public readonly string|null $name = null,
        public readonly string|null $schema = null,
        array|JoinColumn $joinColumns = [],
        array|JoinColumn $inverseJoinColumns = [],
        public readonly string|null $foreignKeyName = null,
        public readonly string|null $inverseForeignKeyName = null,
        public readonly array $options = [],
    ) {
        $this->joinColumns = $joinColumns instanceof JoinColumn ? [$joinColumns] : $joinColumns;
        $this->inverseJoinColumns = $inverseJoinColumns instanceof JoinColumn
            ? [$inverseJoinColumns]
            : $inverseJoinColumns;
    }
}
