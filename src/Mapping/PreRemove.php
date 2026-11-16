<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[PreRemove]: a method called before the entity is deleted.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['PreRemove'].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class PreRemove implements MappingAttribute
{
}
