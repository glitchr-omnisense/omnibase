<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[Index]: an index of the table.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['Index'].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Index implements MappingAttribute
{
    public function __construct(
        public readonly string|null $name = null,
        public readonly array|null $columns = null,
        public readonly array|null $fields = null,
        public readonly array|null $flags = null,
        public readonly array|null $options = null,
    ) {
    }
}
