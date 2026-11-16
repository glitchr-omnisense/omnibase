<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[PrePersist]: a method called before the entity is inserted.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['PrePersist'].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class PrePersist implements MappingAttribute
{
}
