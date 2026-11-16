<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[JoinColumn]: the column of a to-one association (or of a join table, pointing at the owner).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['JoinColumn'].
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class JoinColumn implements MappingAttribute
{
    use JoinColumnProperties;
}
