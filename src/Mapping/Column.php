<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[Column]: a property stored in a column.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['Column'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column implements MappingAttribute
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string|null $name = null,
        public readonly string|null $type = null,
        public readonly int|null $length = null,
        public readonly int|null $precision = null,
        public readonly int|null $scale = null,
        public readonly bool $unique = false,
        public readonly bool $nullable = false,
        public readonly bool $insertable = true,
        public readonly bool $updatable = true,
        public readonly string|null $enumType = null,
        public readonly array $options = [],
        public readonly string|null $columnDefinition = null,
        public readonly string|null $generated = null,
        public readonly bool $index = false,
    ) {
    }
}
