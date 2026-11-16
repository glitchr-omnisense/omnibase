<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[Table]: the table of an entity (its name, schema, options).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['Table'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Table implements MappingAttribute
{
    /**
     * @param array<Index>|null            $indexes
     * @param array<UniqueConstraint>|null $uniqueConstraints
     * @param array<string, mixed>         $options
     */
    public function __construct(
        public readonly string|null $name = null,
        public readonly string|null $schema = null,
        public readonly array|null $indexes = null,
        public readonly array|null $uniqueConstraints = null,
        public readonly array $options = [],
    ) {
    }
}
