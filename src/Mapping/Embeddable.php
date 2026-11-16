<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[Embeddable]: a value object stored in the columns of the entities that embed it.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['Embeddable'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Embeddable implements MappingAttribute
{
}
