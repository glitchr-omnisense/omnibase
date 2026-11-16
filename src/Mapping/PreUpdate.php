<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[PreUpdate]: a method called before the entity is updated.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['PreUpdate'].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class PreUpdate implements MappingAttribute
{
}
