<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[PreFlush]: a method called before the unit of work is flushed.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['PreFlush'].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class PreFlush implements MappingAttribute
{
}
