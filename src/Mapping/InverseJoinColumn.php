<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[InverseJoinColumn]: a column of a join table pointing at the target of a many-to-many association.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['InverseJoinColumn'].
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class InverseJoinColumn implements MappingAttribute
{
    use JoinColumnProperties;
}
