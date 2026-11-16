<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[PostLoad]: a method called once the entity is loaded.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['PostLoad'].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class PostLoad implements MappingAttribute
{
}
