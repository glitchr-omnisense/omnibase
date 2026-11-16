<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[Version]: the version column of optimistic locking.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['Version'].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Version implements MappingAttribute
{
}
