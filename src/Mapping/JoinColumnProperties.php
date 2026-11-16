<?php

namespace Base\Mapping;

/**
 * The parameters #[JoinColumn] and #[InverseJoinColumn] share (omnibase's mapping vocabulary,
 * Base\Mapping).
 */
trait JoinColumnProperties
{
    public function __construct(
        public readonly string|null $name = null,
        public readonly string|null $referencedColumnName = null,
        public readonly bool $deferrable = false,
        public readonly bool $unique = false,
        public readonly bool|null $nullable = null,
        public readonly mixed $onDelete = null,
        public readonly string|null $columnDefinition = null,
        public readonly string|null $fieldName = null,
        public readonly string|null $foreignKeyName = null,
        public readonly array $options = [],
    ) {
    }
}
