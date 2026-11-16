<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[UniqueConstraint]: a unique constraint of the table.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['UniqueConstraint'].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class UniqueConstraint implements MappingAttribute
{
    public function __construct(
        public readonly string|null $name = null,
        public readonly array|null $columns = null,
        public readonly array|null $fields = null,
        public readonly array|null $options = null,
    ) {
    }
}
