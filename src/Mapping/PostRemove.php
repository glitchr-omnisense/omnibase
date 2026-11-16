<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[PostRemove]: a method called once the entity is deleted.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['PostRemove'].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class PostRemove implements MappingAttribute
{
}
