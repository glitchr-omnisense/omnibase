<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[PostUpdate]: a method called once the entity is updated.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['PostUpdate'].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class PostUpdate implements MappingAttribute
{
}
