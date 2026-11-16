<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[PostPersist]: a method called once the entity is inserted.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['PostPersist'].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class PostPersist implements MappingAttribute
{
}
